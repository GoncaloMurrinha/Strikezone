<?php
declare(strict_types=1);

final class MiniRedis {
  private $sock;
  public function __construct(string $host='127.0.0.1', int $port=6379, float $timeout=1.0) {
    $errNo=0; $errStr='';
    $this->sock = @fsockopen($host, $port, $errNo, $errStr, $timeout);
    if (!$this->sock) throw new \RuntimeException("Redis connect fail: $errStr");
    stream_set_timeout($this->sock, 0, 300000); // 300ms
  }
  private function send(array $parts): void {
    $out = '*'.count($parts)."\r\n";
    foreach ($parts as $p) { $b=(string)$p; $out .= '$'.strlen($b)."\r\n".$b."\r\n"; }
    fwrite($this->sock, $out);
  }
  private function readResp() {
    $line = fgets($this->sock);
    if ($line===false) return null;
    $type = $line[0];
    $payload = substr($line,1);
    if ($type==='+') return rtrim($payload);
    if ($type===':') return (int)trim($payload);
    if ($type==='-') throw new \RuntimeException('Redis error: '.trim($payload));
    if ($type==='$') {
      $len = (int)$payload;
      if ($len<0) return null;
      $data = '';
      while (strlen($data) < $len) { $data .= fread($this->sock, $len - strlen($data)); }
      fgets($this->sock); // CRLF
      return $data;
    }
    if ($type==='*') {
      $n = (int)$payload; $arr=[];
      for($i=0;$i<$n;$i++) $arr[] = $this->readResp();
      return $arr;
    }
    return null;
  }
  public function publish(string $chan, string $msg): int {
    $this->send(['PUBLISH', $chan, $msg]);
    return (int)$this->readResp();
  }
  public function get(string $key): ?string {
    $this->send(['GET', $key]);
    $resp = $this->readResp();
    if ($resp === null) return null;
    return (string)$resp;
  }
  public function set(string $key, string $val, ?int $ttl=null): void {
    if ($ttl !== null) { $this->send(['SET', $key, $val, 'EX', (int)$ttl]); }
    else { $this->send(['SET', $key, $val]); }
    $this->readResp();
  }
  public function del(string $key): int {
    $this->send(['DEL', $key]);
    return (int)$this->readResp();
  }
  public function subscribeLoop(string $chan, callable $onMessage, ?callable $shouldStop=null): void {
    $this->send(['SUBSCRIBE', $chan]);
    $this->readResp();
    while (!feof($this->sock)) {
      if ($shouldStop && $shouldStop()) {
        break;
      }
      if (function_exists('connection_aborted') && connection_aborted()) {
        break;
      }
      $read = [$this->sock];
      $write = $except = null;
      $ready = @stream_select($read, $write, $except, 0, 200000); // 200ms
      if ($ready === false) {
        break;
      }
      if ($ready === 0) {
        continue;
      }
      $resp = $this->readResp();
      if (is_array($resp) && ($resp[0] ?? '') === 'message') {
        $res = $onMessage($resp[2]);
        if ($res === false) {
          break;
        }
      }
    }
  }
}
