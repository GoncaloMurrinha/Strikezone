<?php
declare(strict_types=1);

final class FloorEngine {
  public function decide(array $readings, ?int $lastFloor): array {
    // readings: [ ['uuid'=>..., 'major'=>1,'minor'=>2,'rssi'=>-67,'floor'=>1], ... ]
    // Agrupa por floor -> média ponderada por RSSI (mais forte = mais peso)
    $agg = [];
    $total = 0.0;
    foreach ($readings as $r) {
      if (!isset($r['floor'])) continue;
      $f = (int)$r['floor'];
      $w = 1.0 / max(1, (abs((int)$r['rssi']))); // peso inverso ao |rssi|
      $agg[$f] = ($agg[$f] ?? 0) + $w;
      $total += $w;
    }
    if (!$agg) return ['floor'=>$lastFloor ?? 0, 'confidence'=>0.0];
    arsort($agg);
    $cand = (int)array_key_first($agg);
    $conf = (float)($agg[$cand] / ($total ?: 1.0));

    // histerese: só troca se confiança > 0.6 ou se não há lastFloor
    if ($lastFloor === null || $cand === $lastFloor || $conf >= 0.6) {
      return ['floor'=>$cand, 'confidence'=>$conf];
    }
    // mantém piso anterior (ruído)
    return ['floor'=>$lastFloor, 'confidence'=>$conf];
  }
}
