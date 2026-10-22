<?php
/**
 * Xtream Codes Authentication Proxy & Validator
 * Supports multi-DNS failover for IPTV accounts
 */

require_once __DIR__ . '/helpers.php';

class XtreamAuth {
    public static function authenticate($username, $password) {
        $serverUrl = getSetting('xtream_server_url', '');
        if (empty($serverUrl)) {
            return [
                'success' => false,
                'message' => 'Xtream Codes server is not configured in Admin Panel.'
            ];
        }

        // Get DNS list for failover
        $pdo = getDbConnection();
        $dnsList = [$serverUrl];
        $stmt = $pdo->query("SELECT server_url FROM dns_servers WHERE is_active = 1 ORDER BY sort_order ASC");
        while ($row = $stmt->fetch()) {
            if (!empty($row['server_url']) && !in_array($row['server_url'], $dnsList)) {
                $dnsList[] = $row['server_url'];
            }
        }

        $lastError = 'Unable to reach IPTV authentication server.';

        foreach ($dnsList as $dns) {
            $dns = rtrim($dns, '/');
            $url = $dns . '/player_api.php?username=' . urlencode($username) . '&password=' . urlencode($password);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Royal-Media-Store/2.0');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['user_info'])) {
                    $userInfo = $data['user_info'];
                    $auth = (int)($userInfo['auth'] ?? 0);
                    $status = strtolower($userInfo['status'] ?? '');

                    if ($auth === 1 && $status === 'active') {
                        $expTimestamp = (int)($userInfo['exp_date'] ?? 0);
                        $isExpired = false;
                        $expiryDateFormatted = 'Unlimited';
                        $daysLeft = 999;

                        if ($expTimestamp > 0) {
                            $isExpired = (time() > $expTimestamp);
                            $expiryDateFormatted = date('Y-m-d', $expTimestamp);
                            $daysLeft = max(0, ceil(($expTimestamp - time()) / 86400));
                        }

                        if ($isExpired) {
                            return [
                                'success' => false,
                                'message' => 'Your IPTV subscription has expired on ' . $expiryDateFormatted
                            ];
                        }

                        return [
                            'success' => true,
                            'account_type' => 'xtream',
                            'username' => $username,
                            'status' => 'active',
                            'expiry_date' => $expiryDateFormatted,
                            'days_left' => $daysLeft,
                            'max_devices' => (int)($userInfo['max_connections'] ?? 1),
                            'server' => $dns
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Invalid username or password, or account is disabled.'
                        ];
                    }
                }
            } else {
                $lastError = "Connection to $dns failed ($curlError)";
            }
        }

        return [
            'success' => false,
            'message' => $lastError
        ];
    }
}
