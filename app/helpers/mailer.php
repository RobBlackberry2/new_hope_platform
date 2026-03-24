<?php

class Mailer
{
    private array $config;
    private int $timeout = 20;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
    {
        $host = (string)($this->config['host'] ?? '');
        $port = (int)($this->config['port'] ?? 587);
        $username = (string)($this->config['username'] ?? '');
        $password = (string)($this->config['password'] ?? '');
        $fromEmail = (string)($this->config['from_email'] ?? $username);
        $fromName = (string)($this->config['from_name'] ?? 'Sistema');
        $secure = strtolower((string)($this->config['secure'] ?? 'tls'));

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            throw new RuntimeException('La configuración de correo está incompleta.');
        }

        if ($textBody === '') {
            $textBody = trim(html_entity_decode(
                strip_tags(str_replace(
                    ['<br>', '<br/>', '<br />', '</p>'],
                    ["\n", "\n", "\n", "\n\n"],
                    $htmlBody
                )),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
        }

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new RuntimeException("No se pudo conectar al servidor SMTP: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($secure === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

                if ($cryptoEnabled !== true) {
                    throw new RuntimeException('No se pudo activar TLS con el servidor SMTP.');
                }

                $this->command($socket, 'EHLO localhost', [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $boundary = 'b1_' . bin2hex(random_bytes(8));

            $headers = [];
            $headers[] = 'Date: ' . date(DATE_RFC2822);
            $headers[] = 'From: ' . $this->formatAddress($fromEmail, $fromName);
            $headers[] = 'To: ' . $this->formatAddress($toEmail, $toName);
            $headers[] = 'Subject: ' . $this->encodeHeader($subject);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $message = implode("\r\n", $headers) . "\r\n\r\n";
            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $this->normalizeData($textBody) . "\r\n\r\n";
            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $this->normalizeData($htmlBody) . "\r\n\r\n";
            $message .= '--' . $boundary . "--\r\n.";

            fwrite($socket, $message . "\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, array $expectedCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): void
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('El servidor SMTP no respondió.');
        }

        $code = (int)substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error [' . $code . ']: ' . trim($response));
        }
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        $email = trim($email);
        $name = trim($name);

        if ($name === '') {
            return '<' . $email . '>';
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function normalizeData(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/^\./m', '..', $value);
        return str_replace("\n", "\r\n", $value);
    }
}