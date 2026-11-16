<?php

namespace App\Console\Commands;

use App\Mail\AbandonedBookingReminderMail;
use App\Models\BookingSession;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendAbandonedBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-abandoned-reminders 
                            {--force : Process immediately without waiting 24 hours (for testing)}
                            {--email= : Process only a specific customer email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 24-hour reminder email with portal credentials for incomplete/abandoned bookings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning for abandoned booking sessions...');

        $query = BookingSession::query()
            ->where('status', 'pending')
            ->whereNull('reminder_sent_at');

        if ($this->option('email')) {
            $query->where('customer_email', strtolower(trim((string) $this->option('email'))));
        }

        if (!$this->option('force')) {
            $cutoff = now()->subHours(24);
            $query->where('created_at', '<=', $cutoff);
            $this->info("Filtering sessions older than 24 hours (before {$cutoff->toDateTimeString()}).");
        } else {
            $this->warn('Force flag active: processing all pending sessions regardless of creation age.');
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            $this->info('No eligible abandoned booking sessions found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$sessions->count()} session(s) to notify.");

        $sentCount = 0;
        $portalBaseUrl = config('services.freighteva.portal_url', 'https://app.freightmata.com/login');

        foreach ($sessions as $session) {
            $this->line("Processing session ID: {$session->id} for email: {$session->customer_email}");

            try {
                // Determine or generate user password
                $user = User::where('email', $session->customer_email)->first();
                $tempPassword = 'Eva!' . Str::random(8) . '9';

                if (!$user) {
                    $nameParts = explode(' ', trim((string) $session->customer_name), 2);
                    $firstName = $nameParts[0] ?: 'Shipper';
                    $lastName = $nameParts[1] ?? 'Customer';

                    $user = User::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $session->customer_email,
                        'mobile' => $session->customer_phone ?: '',
                        'password' => Hash::make($tempPassword),
                    ]);
                    $this->line("Created portal user account for {$session->customer_email}");
                } else {
                    $user->password = Hash::make($tempPassword);
                    $user->save();
                    $this->line("Reset credentials for existing user {$session->customer_email}");
                }

                $session->portal_user_id = $user->id;

                // Send reminder email
                Mail::to($session->customer_email)
                    ->send(new AbandonedBookingReminderMail($session, $tempPassword, $portalBaseUrl));

                $session->reminder_sent_at = now();
                $session->save();

                $sentCount++;
                $this->info("✓ Reminder email successfully sent to {$session->customer_email}");
            } catch (Exception $e) {
                $this->error("✗ Failed to send reminder to {$session->customer_email}: {$e->getMessage()}");
                Log::error('Abandoned Booking Reminder Error: ' . $e->getMessage(), [
                    'session_id' => $session->id,
                    'email' => $session->customer_email,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Finished: {$sentCount} reminder(s) dispatched.");
        return Command::SUCCESS;
    }
}
