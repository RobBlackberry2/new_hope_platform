<?php
require_once __DIR__ . '/../config/db.php';

class OneDriveToken {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function get(): ?array {
    $res = $this->db->query("SELECT * FROM onedrive_tokens ORDER BY id DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
  }

  public function save(string $refresh, ?string $access, ?string $expires_at): bool {
    $existing = $this->get();
    if ($existing) {
      $stmt = $this->db->prepare("UPDATE onedrive_tokens SET refresh_token=?, access_token=?, expires_at=? WHERE id=?");
      $id = (int)$existing['id'];
      $stmt->bind_param('sssi', $refresh, $access, $expires_at, $id);
      return $stmt->execute();
    }
    $stmt = $this->db->prepare("INSERT INTO onedrive_tokens (refresh_token, access_token, expires_at) VALUES (?,?,?)");
    $stmt->bind_param('sss', $refresh, $access, $expires_at);
    return $stmt->execute();
  }
}