<?php
require_once __DIR__ . '/../vendor/autoload.php';

class GoogleCalendar {
    private ?Google_Client $client = null;

    public function __construct() {
        if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '') {
            return;
        }
        $this->client = new Google_Client();
        $this->client->setClientId(GOOGLE_CLIENT_ID);
        $this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $this->client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $this->client->addScope(Google_Service_Calendar::CALENDAR);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function is_connected(): bool {
        if ($this->client === null) {
            return false;
        }
        $db = get_db();
        $stmt = $db->query('SELECT id FROM google_tokens LIMIT 1');
        return $stmt->fetch() !== false;
    }

    public function get_auth_url(): string {
        if ($this->client === null) {
            throw new RuntimeException('Google client not configured.');
        }
        $state = bin2hex(random_bytes(16));
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['google_oauth_state'] = $state;
        $this->client->setState($state);
        return $this->client->createAuthUrl();
    }

    public function exchange_code(string $code): void {
        if ($this->client === null) {
            throw new RuntimeException('Google client not configured.');
        }
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new RuntimeException('Google OAuth error: ' . $token['error']);
        }
        $this->save_token($token);
    }

    private function save_token(array $token): void {
        $db = get_db();
        $db->exec('DELETE FROM google_tokens');
        $access_token  = json_encode($token);
        $refresh_token = $token['refresh_token'] ?? null;
        $expires_at    = time() + ($token['expires_in'] ?? 3600);
        $stmt = $db->prepare('INSERT INTO google_tokens (access_token, refresh_token, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$access_token, $refresh_token, $expires_at]);
    }

    public function get_client(): ?Google_Client {
        if ($this->client === null || !$this->is_connected()) {
            return null;
        }
        $db = get_db();
        $stmt = $db->query('SELECT * FROM google_tokens ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();
        if (!$row) return null;

        $token = json_decode($row['access_token'], true);
        $this->client->setAccessToken($token);

        // Refresh if expired
        if ($this->client->isAccessTokenExpired()) {
            if ($row['refresh_token']) {
                $this->client->fetchAccessTokenWithRefreshToken($row['refresh_token']);
                $new_token = $this->client->getAccessToken();
                if (!isset($new_token['error'])) {
                    $this->save_token($new_token);
                }
            } else {
                return null;
            }
        }

        return $this->client;
    }

    /**
     * Get busy time intervals for a date from Google Calendar.
     * Returns array of [start_timestamp, end_timestamp] in local timezone.
     */
    public function get_busy_times(string $date): array {
        $client = $this->get_client();
        if (!$client) return [];

        $service = new Google_Service_Calendar($client);

        $tz        = new DateTimeZone(ADMIN_TIMEZONE);
        $day_start = new DateTime($date . ' 00:00:00', $tz);
        $day_end   = new DateTime($date . ' 23:59:59', $tz);

        $request = new Google_Service_Calendar_FreeBusyRequest([
            'timeMin' => $day_start->format(DateTime::RFC3339),
            'timeMax' => $day_end->format(DateTime::RFC3339),
            'items'   => [['id' => 'primary']],
        ]);

        $result = $service->freebusy->query($request);
        $busy   = $result->getCalendars()['primary']->getBusy();

        $intervals = [];
        foreach ($busy as $period) {
            $intervals[] = [
                strtotime($period->getStart()),
                strtotime($period->getEnd()),
            ];
        }

        return $intervals;
    }

    /**
     * Create a Google Calendar event for a booking.
     * Returns the event ID.
     */
    public function create_event(array $booking, array $service): ?string {
        $client = $this->get_client();
        if (!$client) return null;

        $cal_service = new Google_Service_Calendar($client);
        $tz          = ADMIN_TIMEZONE;

        $event = new Google_Service_Calendar_Event([
            'summary'     => $service['name'] . ' - ' . $booking['client_name'],
            'description' => $booking['client_notes'] ?? '',
            'start'       => [
                'dateTime' => (new DateTime($booking['start_datetime']))->format(DateTime::RFC3339),
                'timeZone' => $tz,
            ],
            'end'         => [
                'dateTime' => (new DateTime($booking['end_datetime']))->format(DateTime::RFC3339),
                'timeZone' => $tz,
            ],
            'attendees'   => [
                ['email' => $booking['client_email'], 'displayName' => $booking['client_name']],
            ],
        ]);

        $created = $cal_service->events->insert('primary', $event);
        return $created->getId();
    }

    /**
     * Delete a Google Calendar event by event ID.
     */
    public function delete_event(string $event_id): void {
        $client = $this->get_client();
        if (!$client) return;

        $cal_service = new Google_Service_Calendar($client);
        try {
            $cal_service->events->delete('primary', $event_id);
        } catch (Exception $e) {
            // Event may already be deleted; ignore
        }
    }

    public function disconnect(): void {
        $db = get_db();
        $db->exec('DELETE FROM google_tokens');
    }
}
