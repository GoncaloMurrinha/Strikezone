<?php
declare(strict_types=1);

final class OwnerAuth {
  public function __construct(private Repository $repo) {
    if (session_status()===PHP_SESSION_NONE) session_start();
  }
  public function login(string $email,string $pass): bool {
    $u = $this->repo->findUserByEmail($email);
    if (!$u || !password_verify($pass,$u['pass_hash'])) return false;
    $_SESSION['owner_user_id'] = (int)$u['id'];
    $_SESSION['owner_display'] = (string)$u['display_name'];
    return true;
  }
  public function logout(): void { $_SESSION=[]; session_destroy(); }
  public function requireOwner(): int {
    $id = (int)($_SESSION['owner_user_id'] ?? 0);
    if ($id<=0) { header('Location: /owner/login'); exit; }
    return $id;
  }
}
