<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/OneDriveToken.php';

class OneDriveService
{
  private array $cfg;
  private OneDriveToken $tok;

  public function __construct()
  {
    $config = require __DIR__ . '/../config/config.php';
    $this->cfg = $config['microsoft'];
    $this->tok = new OneDriveToken();
  }

  public function getAuthorizeUrl(): string
  {
    error_log("REDIRECT_URI => " . $this->cfg['redirect_uri']);
    $params = http_build_query([
      'client_id' => $this->cfg['client_id'],
      'response_type' => 'code',
      'redirect_uri' => $this->cfg['redirect_uri'],
      'response_mode' => 'query',
      'scope' => $this->cfg['scopes'],
      'prompt' => 'consent'
    ]);
    return "https://login.microsoftonline.com/{$this->cfg['tenant']}/oauth2/v2.0/authorize?$params";
  }

  public function exchangeCodeForTokens(string $code): array
  {
    $url = "https://login.microsoftonline.com/{$this->cfg['tenant']}/oauth2/v2.0/token";

    $post = [
      'client_id' => $this->cfg['client_id'],
      'client_secret' => $this->cfg['client_secret'],
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $this->cfg['redirect_uri'],
      'scope' => $this->cfg['scopes']
    ];

    $resp = $this->curlJson($url, 'POST', $post);
    if (empty($resp['refresh_token']))
      throw new Exception("No llegó refresh_token. Revisa scope offline_access.");

    $expiresAt = null;
    if (!empty($resp['expires_in'])) {
      $expiresAt = (new DateTime('now'))->add(new DateInterval('PT' . (int) $resp['expires_in'] . 'S'))->format('Y-m-d H:i:s');
    }

    $this->tok->save($resp['refresh_token'], $resp['access_token'] ?? null, $expiresAt);
    return $resp;
  }

  private function looksLikeJwt(?string $token): bool
  {
    return is_string($token) && strpos($token, '.') !== false; // JWT tiene 2 puntos mínimo
  }

  private function encodePath(string $path): string
  {
    $path = trim($path, '/');
    if ($path === '')
      return '';
    $parts = explode('/', $path);
    $parts = array_map('rawurlencode', $parts); // encodea segmentos, no "/"
    return implode('/', $parts);
  }

  public function whoAmI(): array
  {
    return $this->graph('GET', 'https://graph.microsoft.com/v1.0/me');
  }

  public function getAccessToken(): string
  {
    $row = $this->tok->get();
    if (!$row)
      throw new Exception("OneDrive no está conectado. Primero autoriza la cuenta.");

    // si aún sirve el access_token (y parece JWT)
    if (!empty($row['access_token']) && !empty($row['expires_at'])) {
      $now = new DateTime('now');
      $exp = new DateTime($row['expires_at']);
      if ($now < $exp->sub(new DateInterval('PT60S'))) { // margen
        return $row['access_token'];
      }
    }

    // Si hay access_token pero NO es JWT, lo tratamos como inválido y forzamos refresh

    // refresh
    $url = "https://login.microsoftonline.com/{$this->cfg['tenant']}/oauth2/v2.0/token";
    $post = [
      'client_id' => $this->cfg['client_id'],
      'client_secret' => $this->cfg['client_secret'],
      'grant_type' => 'refresh_token',
      'refresh_token' => $row['refresh_token'],
      'scope' => $this->cfg['scopes']
    ];
    $resp = $this->curlJson($url, 'POST', $post);

    $newRefresh = $resp['refresh_token'] ?? $row['refresh_token'];
    $expiresAt = null;
    if (!empty($resp['expires_in'])) {
      $expiresAt = (new DateTime('now'))->add(new DateInterval('PT' . (int) $resp['expires_in'] . 'S'))->format('Y-m-d H:i:s');
    }
    $this->tok->save($newRefresh, $resp['access_token'] ?? null, $expiresAt);

    $token = $resp['access_token'] ?? '';
    if (!$token) {
      throw new Exception("No llegó access_token en refresh.");
    }
    return $token;
  }

  // ---------- OneDrive ops ----------
  private function graph(string $method, string $url, ?array $json = null): array
  {
    $token = $this->getAccessToken();

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
      'Authorization: Bearer ' . $token,
      $json ? 'Content-Type: application/json' : null
    ]));
    if ($json !== null)
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $body ? json_decode($body, true) : [];
    if ($code >= 400) {
      $msg = $data['error']['message'] ?? $body ?? 'Graph error';
      throw new Exception("Graph $code: $msg");
    }
    return $data ?: [];
  }

  // Crea upload session en una ruta y sube el archivo por chunks
  public function uploadLocalFileToPath(string $onedrivePath, string $tmpPath, string $originalName): array
  {
    $root = trim($this->cfg['onedrive_root'], '/');
    $cleanPath = trim($onedrivePath, '/');
    $fullPath = $root . '/' . $cleanPath . '/' . $originalName;
    $fullPath = preg_replace('#/+#', '/', $fullPath);

    // createUploadSession:
    $url = "https://graph.microsoft.com/v1.0/me/drive/root:/" . $this->encodePath($fullPath) . ":/createUploadSession";
    $session = $this->graph('POST', $url, [
      'item' => ['@microsoft.graph.conflictBehavior' => 'replace']
    ]);

    $uploadUrl = $session['uploadUrl'] ?? null;
    if (!$uploadUrl)
      throw new Exception("No se pudo crear upload session.");

    $size = filesize($tmpPath);
    $handle = fopen($tmpPath, 'rb');
    if (!$handle)
      throw new Exception("No se pudo leer archivo temporal.");

    $chunkSize = 5 * 1024 * 1024; // 5MB
    $start = 0;

    while (!feof($handle)) {
      $data = fread($handle, $chunkSize);
      $end = $start + strlen($data) - 1;

      $ch = curl_init($uploadUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Length: " . strlen($data),
        "Content-Range: bytes {$start}-{$end}/{$size}"
      ]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

      $respBody = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      // 202 = accepted (más chunks), 201/200 = terminado
      if (!in_array($code, [200, 201, 202], true)) {
        throw new Exception("Upload chunk falló ($code): " . $respBody);
      }

      if (in_array($code, [200, 201], true)) {
        $item = json_decode($respBody, true);
        fclose($handle);
        return $item; // contiene id (itemId), name, size, etc.
      }

      $start = $end + 1;
    }

    fclose($handle);
    throw new Exception("Upload no terminó correctamente.");
  }

  // Devuelve URL final de descarga (Graph /content redirige)
  public function getDownloadUrl(string $itemId): string
  {
    $token = $this->getAccessToken();
    $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . rawurlencode($itemId) . "/content";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $header = curl_getinfo($ch);
    curl_close($ch);

    // Algunos entornos no llenan REDIRECT_URL; alternativa: hacer GET con follow=0 y parsear headers.
    if ($code === 302 && $location)
      return $location;

    // fallback: pedir metadata y usar @microsoft.graph.downloadUrl
    $meta = $this->graph('GET', "https://graph.microsoft.com/v1.0/me/drive/items/" . rawurlencode($itemId));
    $dl = $meta['@microsoft.graph.downloadUrl'] ?? null;
    if (!$dl)
      throw new Exception("No se pudo obtener downloadUrl.");
    return $dl;
  }

  public function deleteItem(string $itemId): void
  {
    $this->graph('DELETE', "https://graph.microsoft.com/v1.0/me/drive/items/" . rawurlencode($itemId));
  }

  private function curlJson(string $url, string $method, array $post): array
  {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $body ? json_decode($body, true) : [];
    if ($code >= 400) {
      $msg = $data['error_description'] ?? $data['error']['message'] ?? ($body ?? 'OAuth error');
      throw new Exception("OAuth $code: $msg");
    }
    return $data ?: [];
  }
}