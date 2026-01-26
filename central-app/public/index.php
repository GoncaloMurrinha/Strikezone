<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Repository.php';
require __DIR__ . '/../src/FloorEngine.php';
require __DIR__ . '/../src/Jwt.php';
require __DIR__ . '/../src/Realtime.php';
require __DIR__ . '/../src/ApiController.php';
require __DIR__ . '/../src/OwnerAuth.php';
require __DIR__ . '/../src/QrManager.php';

$config = require __DIR__ . '/../src/config.php';
$qr = new QrManager($config['qr']);
$pdo = (new DB($config['db']))->pdo();
$repo = new Repository($pdo);
$fe = new FloorEngine();
$rt = new Realtime($config['redis']);
$api = new ApiController($repo,$fe,$rt,$qr,$config);
$auth = new OwnerAuth($repo);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- API ---------- */
if ($uri === '/api/register.php') {require __DIR__.'/api/register.php'; exit;}
if ($uri==='/api/register' && $method==='POST') { $api->register(); exit; }
if ($uri==='/api/login'    && $method==='POST') { $api->login(); exit; }
if ($uri==='/api/code/resolve') { $api->codeResolve(); exit; }
if ($uri === '/register') { readfile(__DIR__ . '/register.html'); exit; }

if ($uri==='/api/arena/create' && $method==='POST') { $api->arenaCreate(); exit; }
if ($uri==='/api/arena/list'   && $method==='GET')  { $api->arenaList(); exit; }

if ($uri==='/api/match/create' && $method==='POST') { $api->matchCreate(); exit; }
if ($uri==='/api/match/list'   && $method==='GET')  { $api->matchList(); exit; }
if ($uri==='/api/match/join'   && $method==='POST') { $api->matchJoin(); exit; }
if ($uri==='/api/match/register-player' && $method==='POST') { $api->matchRegisterPlayer(); exit; }
if ($uri==='/api/match/roster' && $method==='GET')  { $api->matchRoster(); exit; }
if ($uri==='/api/match/team-roster' && $method==='GET') { $api->teamRoster(); exit; }

if ($uri==='/api/scan' && $method==='POST') { $api->submitScan(); exit; }
if ($uri==='/api/maps' && $method==='GET') { $api->mapList(); exit; }

/* ---------- OWNER UI ---------- */
function page_header($title='Owner'){
  $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  echo "<!doctype html><html lang='pt'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<title>$safeTitle</title>";
  echo "<link rel='preconnect' href='https://fonts.googleapis.com'>";
  echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>";
  echo "<link href='https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap' rel='stylesheet'>";
  echo "<link rel='stylesheet' href='/assets/app.css'>";
  echo "</head><body class='dash-body'><div class='dashboard-shell'>";
  echo "<header class='top-nav'>";
  echo "<div class='brand'><img src='/assets/logo_strikezone.png' alt='StrikeZone'><span>StrikeZone Central</span></div>";
  echo "<nav><a href='/owner' class='btn btn-ghost'>Dashboard</a><a href='/owner/maps' class='btn btn-ghost'>Mapas</a><a href='/owner/beacons' class='btn btn-ghost'>Beacons</a><a href='/owner/logout' class='btn btn-ghost'>Sair</a></nav>";
  echo "</header><main class='dash-main'><h1>$safeTitle</h1><div id='page-loading' class='page-overlay' hidden><div class='spinner'></div><p>A carregar…</p></div>";
}
function page_footer(){ echo "</main></div></body></html>"; }

if ($uri==='/owner/login') {
  if ($method==='POST') {
    $ok = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok) { header('Location: /owner'); exit; }
    $err = "Credenciais inválidas";
  }
  $errBlock = '';
  if (!empty($err)) {
    $errEsc = htmlspecialchars($err, ENT_QUOTES, 'UTF-8');
    $errBlock = "<div class='alert'>$errEsc</div>";
  }
  echo "<!doctype html>
<html lang='pt'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>Login - StrikeZone</title>
  <link rel='preconnect' href='https://fonts.googleapis.com'>
  <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
  <link href='https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap' rel='stylesheet'>
  <style>
    :root {
      color-scheme: dark;
      --bg:#0f1230;
      --panel:#1a1f4d;
      --accent:#9f7eff;
      --accent-strong:#f64b9b;
      --text:#f2f3ff;
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      background:radial-gradient(circle at top, #1c2362 0%, #0d0f29 60%);
      font-family:'Space Grotesk',system-ui,sans-serif;
      color:var(--text);
      padding:2rem;
    }
    .auth-card {
      width:min(420px, 100%);
      background:rgba(15,18,48,0.85);
      border:1px solid rgba(255,255,255,0.1);
      border-radius:18px;
      box-shadow:0 30px 60px rgba(6,8,24,0.6);
      padding:2.5rem;
      text-align:center;
      backdrop-filter: blur(18px);
    }
    .logo {
      width:90px;
      margin:0 auto 1rem auto;
      display:block;
    }
    h1 { font-size:1.9rem; margin-bottom:0.5rem; }
    .subtitle { color:rgba(242,243,255,0.65); margin-bottom:2rem; }
    label { display:block; text-align:left; font-weight:500; margin-bottom:0.4rem; }
    input[type=email], input[type=password] {
      width:100%;
      border:1px solid rgba(255,255,255,0.15);
      background:rgba(12,16,44,0.8);
      border-radius:12px;
      padding:0.9rem 1rem;
      color:var(--text);
      font-size:1rem;
      margin-bottom:1.2rem;
      outline:none;
      transition:border-color 0.2s, box-shadow 0.2s;
    }
    input:focus {
      border-color:var(--accent);
      box-shadow:0 0 0 3px rgba(159,126,255,0.2);
    }
    .actions {
      display:flex;
      gap:0.75rem;
      margin-top:0.5rem;
    }
    .btn {
      flex:1;
      border:none;
      cursor:pointer;
      border-radius:999px;
      font-weight:600;
      padding:0.9rem 1rem;
      font-size:1rem;
      transition:transform 0.2s, box-shadow 0.2s;
    }
    .btn-primary {
      background:linear-gradient(135deg, var(--accent), var(--accent-strong));
      color:#fff;
      box-shadow:0 15px 30px rgba(246,75,155,0.35);
    }
    .btn-secondary {
      background:rgba(255,255,255,0.08);
      color:#fff;
      border:1px solid rgba(255,255,255,0.1);
    }
    .btn:hover { transform:translateY(-1px); }
    .btn:active { transform:translateY(1px); }
    .alert {
      background:rgba(255,82,125,0.1);
      border:1px solid rgba(255,82,125,0.3);
      color:#ff8ea5;
      padding:0.75rem 1rem;
      border-radius:10px;
      margin-bottom:1rem;
      text-align:left;
      font-size:0.95rem;
    }
  </style>
</head>
<body>
  <div class='auth-card'>
    <img class='logo' src='/assets/logo_strikezone.png' alt='StrikeZone'>
    <h1>Login</h1>
    <p class='subtitle'>Acede ao dashboard para gerir arenas e partidas.</p>
    $errBlock
    <form method='post'>
      <label for='email'>E-mail</label>
      <input id='email' name='email' type='email' placeholder='owner@strikezone.pt' required>
      <label for='password'>Password</label>
      <input id='password' name='password' type='password' placeholder='••••••••' required>
      <div class='actions'>
        <button type='submit' class='btn btn-primary'>Entrar</button>
        <a class='btn btn-secondary' href='/' role='button'>Voltar</a>
      </div>
    </form>
  </div>
</body>
</html>";
  exit;
}
if ($uri==='/owner/logout') { $auth->logout(); header('Location: /owner/login'); exit; }

if ($uri==='/owner') {
  $ownerId = $auth->requireOwner();
  if ($method==='POST' && isset($_POST['create_arena'])) {
    $name = trim($_POST['arena_name'] ?? '');
    if ($name!=='') $repo->createArena($ownerId,$name);
    header('Location: /owner'); exit;
  }
  $arenas = $repo->listArenasByOwner($ownerId);
  page_header("Painel do Campo");
  echo "<div class='grid-2'>";
  echo "<section class='card'><h2>Os meus Campos</h2>";
  if (!$arenas) {
    echo "<p class='muted'>(ainda sem campos)</p>";
  } else {
    echo "<div class='list-flex'>";
    foreach ($arenas as $a) {
      $name = htmlspecialchars($a['name']);
      $id = (int)$a['id'];
      echo "<div class='item-row'><div><strong>$name</strong><br><span class='muted'>ID #$id</span></div><a class='btn btn-ghost' href='/owner/arena/$id'>Abrir</a></div>";
    }
    echo "</div>";
  }
  echo "</section>";
  echo "<section class='card'><h2>Novo Campo</h2>";
  echo "<form method='post'>
    <input type='hidden' name='create_arena' value='1'>
    <div class='form-group'>
      <label for='arena_name'>Nome do Campo</label>
      <input id='arena_name' name='arena_name' required>
    </div>
    <button type='submit' class='btn'>Criar Campo</button>
  </form></section>";
  echo "</div>";
  page_footer(); exit;
}

if (preg_match('#^/owner/arena/(\d+)$#', $uri, $m)) {
  $ownerId = $auth->requireOwner();
  $arenaId = (int)$m[1];
  $ok=false; foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===$arenaId) $ok=true;
  if (!$ok){ http_response_code(403); exit('forbidden'); }

  if ($method==='POST' && isset($_POST['create_match'])) {
    $name = trim($_POST['name'] ?? '');
    $start= trim($_POST['starts_at'] ?? '');
    $ta   = trim($_POST['team_a'] ?? 'Azuis');
    $tb   = trim($_POST['team_b'] ?? 'Vermelhos');
    $mode = strtolower((string)($_POST['code_mode'] ?? 'text'));
    if (!in_array($mode, ['text','qr'], true)) { $mode = 'text'; }
    if ($name && $start){
      $codeA = ApiController::randomCode(6);
      $codeB = ApiController::randomCode(6);
      $mid = $repo->createMatch($arenaId,$name,$start,$ta,$tb,$codeA,$codeB,$mode);
      if ($mode === 'qr') {
        try { $qr->ensureForMatch($mid, $codeA, $codeB); } catch (\Throwable $e) { error_log('QR gen failed: '.$e->getMessage()); }
      }
      header("Location: /owner/match/$mid"); exit;
    }
  }

  $matches = $repo->listMatchesByArena($arenaId);
  $maps = $repo->listMapsByArena($arenaId);

  page_header("Campo #$arenaId");
  echo "<a class='btn btn-ghost' href='/owner'>&larr; Voltar ao painel</a>";
  echo "<section class='card' style='margin-top:1.5rem;'>";
  echo "<div class='row-between'><h2>Jogos</h2><span class='pill'>".count($matches)." registos</span></div>";
  if (!$matches) {
    echo "<p class='muted'>(ainda sem jogos)</p>";
  } else {
    echo "<table class='table'><thead><tr><th>ID</th><th>Nome</th><th>Início</th><th>Equipas</th><th>Códigos</th><th></th></tr></thead><tbody>";
    foreach ($matches as $mrow) {
      $mid = (int)$mrow['id'];
      $name = htmlspecialchars($mrow['name']);
      $start = htmlspecialchars($mrow['starts_at']);
      $teams = htmlspecialchars($mrow['team_a_name'])." vs ".htmlspecialchars($mrow['team_b_name']);
      $codeA = htmlspecialchars($mrow['team_a_code']);
      $codeB = htmlspecialchars($mrow['team_b_code']);
      $codeMode = htmlspecialchars($mrow['code_display_mode'] ?? 'text');
      $modeBadge = $codeMode === 'qr' ? "<span class='pill pill-muted'>QR</span>" : '';
      echo "<tr>
        <td>$mid</td>
        <td>$name</td>
        <td>$start</td>
        <td>$teams</td>
        <td>A=<code>$codeA</code> B=<code>$codeB</code> $modeBadge</td>
        <td><a class='btn btn-ghost' href='/owner/match/$mid'>Ver ao vivo</a></td>
      </tr>";
    }
    echo "</tbody></table>";
  }
  echo "</section>";

  echo "<div class='grid-2' style='margin-top:1.5rem;'>";
  echo "<section class='card'><h3>Novo Jogo</h3>
    <form method='post'>
      <input type='hidden' name='create_match' value='1'>
      <div class='form-group'><label for='match_name'>Nome</label><input id='match_name' name='name' required></div>
      <div class='form-group'><label for='match_start'>Início (YYYY-MM-DD HH:MM:SS)</label><input id='match_start' name='starts_at' required></div>
      <div class='form-group'><label for='team_a'>Equipa A</label><input id='team_a' name='team_a' value='Azuis'></div>
      <div class='form-group'><label for='team_b'>Equipa B</label><input id='team_b' name='team_b' value='Vermelhos'></div>
      <div class='form-group'>
        <label>Como prefere partilhar os códigos?</label>
        <label class='radio-inline'><input type='radio' name='code_mode' value='text' checked> Mostrar em texto</label>
        <label class='radio-inline'><input type='radio' name='code_mode' value='qr'> Gerar QR Codes</label>
      </div>
      <button type='submit' class='btn'>Criar jogo</button>
    </form></section>";

  echo "<section class='card'><h3>Mapas por piso</h3>";
  if ($maps){
    echo "<ul class='map-list'>";
    foreach ($maps as $mp){
      $floor = (int)$mp['floor'];
      $url = htmlspecialchars($mp['map_url']);
      echo "<li>Piso $floor — <a href='$url' target='_blank'>ver</a></li>";
    }
    echo "</ul>";
  } else {
    echo "<p class='muted'>(Sem mapas ainda, carrega-os em <a href='/owner/maps'>Mapas</a>)</p>";
  }
  echo "</section></div>";

  page_footer(); exit;
}

if (preg_match('#^/owner/match/(\d+)$#', $uri, $m)) {
  $ownerId = $auth->requireOwner();
  $matchId = (int)$m[1];
  $match = $repo->getMatchById($matchId);
  if (!$match){ http_response_code(404); exit('not found'); }
  $ok=false; foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===(int)$match['arena_id']) $ok=true;
  if (!$ok){ http_response_code(403); exit('forbidden'); }

  $members = $repo->listMembersByMatch($matchId);
  $initialState = [];
  foreach ($members as $mbr) {
    $side = $mbr['side'];
    $teamId = $matchId*10 + ($side==='A'?1:2);
    $playerId = $repo->ensurePlayer((int)$mbr['user_id'],$teamId);
    $st = $repo->getPlayerState($playerId);
    $initialState[(int)$mbr['user_id']] = [
      'name'=>$mbr['display_name'],
      'side'=>$side,
      'floor'=>$st['last_floor']!==null ? (int)$st['last_floor'] : null,
      'conf'=>null
    ];
  }
  $maps = $repo->listMapsByArena((int)$match['arena_id']);

  page_header("Ao vivo - Match #$matchId");
  $backUrl = "/owner/arena/{$match['arena_id']}";
  echo "<a class='btn btn-ghost' id='leave-match' href='$backUrl'>&larr; Voltar ao campo</a>";
  echo "<section class='card' style='margin-top:1.5rem;'>";
  $codeMode = $match['code_display_mode'] ?? 'text';
  $teamACode = htmlspecialchars($match['team_a_code']);
  $teamBCode = htmlspecialchars($match['team_b_code']);
  echo "<h2>".htmlspecialchars($match['name'])."</h2>";
  echo "<p class='muted'>A: ".htmlspecialchars($match['team_a_name'])." &nbsp;&nbsp; B: ".htmlspecialchars($match['team_b_name'])."</p>";
  if ($codeMode === 'qr') {
    $qrAUrl = $qr->urlFor($matchId,'A');
    $qrBUrl = $qr->urlFor($matchId,'B');
    if (!$qrAUrl || !$qrBUrl) {
      try { $qr->ensureForMatch($matchId, $match['team_a_code'], $match['team_b_code']); } catch (\Throwable $e) { error_log('QR regen failed: '.$e->getMessage()); }
      $qrAUrl = $qr->urlFor($matchId,'A');
      $qrBUrl = $qr->urlFor($matchId,'B');
    }
    if ($qrAUrl && $qrBUrl) {
      $qrA = htmlspecialchars($qrAUrl);
      $qrB = htmlspecialchars($qrBUrl);
      $teamALabel = htmlspecialchars($match['team_a_name']);
      $teamBLabel = htmlspecialchars($match['team_b_name']);
      echo "<div class='qr-grid'>";
      echo "<div class='qr-card'><div class='team-label'>$teamALabel</div><img src='$qrA' alt='QR Equipa A'><span class='muted'>Código: <code>$teamACode</code></span></div>";
      echo "<div class='qr-card'><div class='team-label'>$teamBLabel</div><img src='$qrB' alt='QR Equipa B'><span class='muted'>Código: <code>$teamBCode</code></span></div>";
      echo "</div>";
    } else {
      echo "<p class='muted'>Códigos — A=<code>$teamACode</code> | B=<code>$teamBCode</code></p>";
    }
  } else {
    echo "<p class='muted'>Códigos — A=<code>$teamACode</code> | B=<code>$teamBCode</code></p>";
  }
  echo "</section>";

  echo "<section class='card' style='margin-top:1.5rem;'>";
  echo "<div class='live-grid' id='live'>";
  echo "<div><h3>Equipa A</h3><ul id='teamA'></ul></div>";
  echo "<div><h3>Equipa B</h3><ul id='teamB'></ul></div>";
  echo "</div></section>";

  if ($maps){
    echo "<section class='card' style='margin-top:1.5rem;'><h3>Mapas por piso</h3><ul class='map-list'>";
    foreach ($maps as $mp){
      echo "<li>Piso ".(int)$mp['floor']." - <a href='".htmlspecialchars($mp['map_url'])."' target='_blank'>ver</a></li>";
    }
    echo "</ul></section>";
  }

  $initialStateJson = json_encode($initialState, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
  $backUrlJs = json_encode($backUrl, JSON_UNESCAPED_SLASHES);
  echo "<script>
    const matchId = $matchId;
    const backUrl = $backUrlJs;
    const A = document.getElementById('teamA');
    const B = document.getElementById('teamB');
    const state = $initialStateJson;
    function render(){
      A.innerHTML=''; B.innerHTML='';
      const arr = Object.values(state);
      arr.sort((x,y)=> (x.side.localeCompare(y.side)) || (x.name.localeCompare(y.name)));
      for (const it of arr){
        const li = document.createElement('li');
        li.className = 'list-group-item bg-transparent text-light';
        li.textContent = it.name + ' — piso: ' + (it.floor??'—') + (it.conf?(' ('+Math.round(it.conf*100)+'%)'):'');
        (it.side==='A'?A:B).appendChild(li);
      }
    }
    render();
    const es = new EventSource('/stream_match.php?match_id='+matchId);
    es.addEventListener('pos', ev=>{
      try{
        const j = JSON.parse(ev.data);
        const side = (j.team_id % 10)===1 ? 'A' : 'B';
        state[j.user.id] = { name:j.user.name, floor:j.pos.floor, conf:j.pos.conf, side };
        render();
      }catch(e){}
    });
    const stopStream = ()=>{
      if (es.readyState !== EventSource.CLOSED) {
        es.close();
      }
    };
    const leaveBtn = document.getElementById('leave-match');
    if (leaveBtn) {
      leaveBtn.addEventListener('click', (ev) => {
        ev.preventDefault();
        stopStream();
        const data = new URLSearchParams({halt:'1'}).toString();
        const url = '/stream_match.php?match_id='+matchId;
        const fallbackFetch = () => {
          fetch(url, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:data,
            keepalive:true
          }).catch(()=>{});
        };
        if (!(navigator.sendBeacon && navigator.sendBeacon(url, new Blob([data], {type:'application/x-www-form-urlencoded'})))) {
          fallbackFetch();
        }
        setTimeout(()=>{ window.location.href = backUrl; }, 10);
      });
    }
    window.addEventListener('beforeunload', stopStream);
    window.addEventListener('pagehide', stopStream);
  </script>";

  page_footer(); exit;
}

if ($uri==='/owner/maps') {
  $ownerId = $auth->requireOwner();
  $cfgUp = $config['uploads'];
  if (!is_dir($cfgUp['maps_dir'])) @mkdir($cfgUp['maps_dir'], 0775, true);

  $parseSizeToBytes = static function (?string $val): int {
    if ($val === null) return 0;
    $v = trim($val);
    if ($v === '') return 0;
    $unit = strtolower(substr($v, -1));
    $num = (float)$v;
    switch ($unit) {
      case 'g': return (int)round($num * 1024 * 1024 * 1024);
      case 'm': return (int)round($num * 1024 * 1024);
      case 'k': return (int)round($num * 1024);
      default: return (int)round($num);
    }
  };
  $phpUploadBytes = $parseSizeToBytes(ini_get('upload_max_filesize') ?: null);
  $phpPostBytes = $parseSizeToBytes(ini_get('post_max_size') ?: null);
  $effectivePhpBytes = 0;
  if ($phpUploadBytes>0 && $phpPostBytes>0) {
    $effectivePhpBytes = min($phpUploadBytes, $phpPostBytes);
  } elseif ($phpUploadBytes>0) {
    $effectivePhpBytes = $phpUploadBytes;
  } elseif ($phpPostBytes>0) {
    $effectivePhpBytes = $phpPostBytes;
  }
  $effectivePhpMb = $effectivePhpBytes>0 ? round($effectivePhpBytes/1048576,1) : null;
  $cfgLimitMb = max(1, (int)$cfgUp['max_mb']);

  $flash = $_SESSION['owner_maps_flash'] ?? null;
  unset($_SESSION['owner_maps_flash']);

  $uploadErrorMessage = static function (int $code): string {
    return match ($code) {
      UPLOAD_ERR_INI_SIZE => 'O ficheiro excede o limite configurado no servidor.',
      UPLOAD_ERR_FORM_SIZE => 'O ficheiro excede o limite definido no formulário.',
      UPLOAD_ERR_PARTIAL => 'O upload ficou incompleto.',
      UPLOAD_ERR_NO_FILE => 'Nenhum ficheiro foi enviado.',
      UPLOAD_ERR_NO_TMP_DIR => 'Falta o diretório temporário.',
      UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever no disco.',
      UPLOAD_ERR_EXTENSION => 'Uma extensão bloqueou o envio.',
      default => 'Erro desconhecido (código '.$code.').'
    };
  };
  $parseOptionalFloat = static function (?string $val): ?float {
    if ($val === null) return null;
    $v = trim($val);
    if ($v === '') return null;
    return (float)$v;
  };

  if ($method==='POST' && isset($_POST['upload_map'])) {
    $arenaId = (int)($_POST['arena_id'] ?? 0);
    $floor   = (int)($_POST['floor'] ?? 0);
    $width = $parseOptionalFloat($_POST['width'] ?? null);
    $height = $parseOptionalFloat($_POST['height'] ?? null);
    try {
      if ($arenaId<=0) {
        throw new RuntimeException('Campo inválido.');
      }
      if ($width !== null && $width <= 0) {
        throw new RuntimeException('Largura inválida.');
      }
      if ($height !== null && $height <= 0) {
        throw new RuntimeException('Altura inválida.');
      }
      if (empty($_FILES['mapfile']['name'])) {
        throw new RuntimeException('Escolhe um ficheiro antes de enviar.');
      }
      $f = $_FILES['mapfile'];
      if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload: '.$uploadErrorMessage((int)$f['error']));
      }
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) {
        throw new RuntimeException('Extensão inválida (aceite: png/jpg/jpeg/webp/svg).');
      }
      if ($f['size'] > $cfgUp['max_mb']*1024*1024) {
        throw new RuntimeException('Ficheiro grande demais (limite '.$cfgUp['max_mb'].'MB).');
      }
      $name = 'map_a'.$arenaId.'_floor_'.$floor.'_'.time().'.'.$ext;
      $dest = rtrim($cfgUp['maps_dir'],'/\\').DIRECTORY_SEPARATOR.$name;
      if (!@move_uploaded_file($f['tmp_name'], $dest)) {
        throw new RuntimeException('Não foi possível gravar o ficheiro (ver permissões de escrita em uploads/maps).');
      }
      $url  = rtrim($cfgUp['maps_url'],'/').'/'.$name;
      $thisArena = null;
      foreach ($repo->listArenasByOwner($ownerId) as $a) {
        if ((int)$a['id']===$arenaId) { $thisArena=$a; break; }
      }
      if (!$thisArena) {
        throw new RuntimeException('Não tens acesso a esse campo.');
      }
      $repo->upsertMap($arenaId, $floor, 'Piso '.$floor, $url, $width, $height);
      error_log(sprintf('[owner/maps] owner=%d arena=%d floor=%d map stored as %s', $ownerId, $arenaId, $floor, $name));
      $_SESSION['owner_maps_flash'] = ['type'=>'success','msg'=>'Mapa carregado com sucesso.'];
    } catch (Throwable $ex) {
      $msg = $ex->getMessage();
      error_log(sprintf('[owner/maps] owner=%d arena=%d floor=%d upload failed: %s', $ownerId, $arenaId, $floor, $msg));
      $_SESSION['owner_maps_flash'] = ['type'=>'error','msg'=>$msg];
    }
    header('Location: /owner/maps'); exit;
  }
  if ($method==='POST' && isset($_POST['update_map_dimensions'])) {
    $arenaId = (int)($_POST['arena_id'] ?? 0);
    $floor   = (int)($_POST['floor'] ?? 0);
    $width = $parseOptionalFloat($_POST['width'] ?? null);
    $height = $parseOptionalFloat($_POST['height'] ?? null);
    try {
      if ($arenaId<=0) {
        throw new RuntimeException('Campo inválido.');
      }
      if ($floor < 0) {
        throw new RuntimeException('Piso inválido.');
      }
      if ($width !== null && $width <= 0) {
        throw new RuntimeException('Largura inválida.');
      }
      if ($height !== null && $height <= 0) {
        throw new RuntimeException('Altura inválida.');
      }
      $thisArena = null;
      foreach ($repo->listArenasByOwner($ownerId) as $a) {
        if ((int)$a['id']===$arenaId) { $thisArena=$a; break; }
      }
      if (!$thisArena) {
        throw new RuntimeException('Não tens acesso a esse campo.');
      }
      $repo->updateMapDimensions($arenaId, $floor, $width, $height);
      $_SESSION['owner_maps_flash'] = ['type'=>'success','msg'=>'Dimensões atualizadas.'];
    } catch (Throwable $ex) {
      $msg = $ex->getMessage();
      $_SESSION['owner_maps_flash'] = ['type'=>'error','msg'=>$msg];
    }
    header('Location: /owner/maps'); exit;
  }

  $arenas = $repo->listArenasByOwner($ownerId);
  page_header("Mapas do Campo");
  echo "<section class='card'><h2>Carregar mapa por piso</h2>";
  if ($flash) {
    $cls = $flash['type']==='success' ? "alert success" : "alert";
    $msg = htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8');
    echo "<div class='$cls'>$msg</div>";
  }
  if ($effectivePhpMb !== null) {
    $info = "Limite real do PHP para uploads: {$effectivePhpMb}MB (upload_max_filesize/post_max_size).";
    if ($effectivePhpMb < $cfgLimitMb) {
      $info .= " Está abaixo dos {$cfgLimitMb}MB definidos na app — aumenta os valores em php.dev.ini e arranca o servidor com `php -S 0.0.0.0:8080 -t public -c php.dev.ini`.";
      echo "<div class='alert'>$info</div>";
    } else {
      echo "<div class='alert info'>$info</div>";
    }
  } else {
    echo "<div class='alert info'>Não foi possível determinar o limite de upload do PHP; se precisares de mais que {$cfgLimitMb}MB ajusta php.dev.ini.</div>";
  }
  if (!$arenas) echo "<p class='muted'>(cria primeiro um Campo)</p>";
  echo "<form method='post' enctype='multipart/form-data' class='upload-zone' style='max-width:600px'>
    <input type='hidden' name='upload_map' value='1'>
    <div class='form-group'><label for='arena_id'>Campo</label><select id='arena_id' name='arena_id' required>";
  foreach ($arenas as $a) {
    echo "<option value='{$a['id']}'>".htmlspecialchars($a['name'])."</option>";
  }
  echo "</select></div>
    <div class='form-group'><label for='floor'>Piso</label><input id='floor' type='number' name='floor' required></div>
    <div class='form-group'><label for='width'>Largura (metros)</label><input id='width' type='number' step='0.01' name='width' placeholder='Opcional'></div>
    <div class='form-group'><label for='height'>Altura (metros)</label><input id='height' type='number' step='0.01' name='height' placeholder='Opcional'></div>
    <div class='form-group'><label for='mapfile'>Ficheiro (png/jpg/webp/svg, max ".(int)$config['uploads']['max_mb']."MB)</label><input id='mapfile' type='file' name='mapfile' required></div>
    <button type='submit' class='btn'>Enviar mapa</button>
  </form></section>";

  $allMaps = [];
  foreach ($arenas as $a) {
    $arenaMaps = $repo->listMapsByArena((int)$a['id']);
    if ($arenaMaps) {
      $allMaps[(int)$a['id']] = ['name'=>$a['name'],'maps'=>$arenaMaps];
    }
  }
  if ($allMaps) {
    echo "<section class='card' style='margin-top:1.5rem;'><h2>Dimensões atuais</h2>";
    foreach ($allMaps as $aid => $bundle) {
      $aName = htmlspecialchars($bundle['name']);
      echo "<h3>$aName</h3>";
      echo "<table class='table'><thead><tr><th>Piso</th><th>Largura</th><th>Altura</th><th>Mapa</th></tr></thead><tbody>";
      foreach ($bundle['maps'] as $mp) {
        $floor = (int)$mp['floor'];
        $widthVal = $mp['width'] !== null ? htmlspecialchars((string)$mp['width']) : '—';
        $heightVal = $mp['height'] !== null ? htmlspecialchars((string)$mp['height']) : '—';
        $url = htmlspecialchars($mp['map_url']);
        echo "<tr><td>$floor</td><td>$widthVal</td><td>$heightVal</td><td><a href='$url' target='_blank'>ver</a></td></tr>";
      }
      echo "</tbody></table>";
    }
    echo "</section>";
  }

  echo "<section class='card' style='margin-top:1.5rem;'><h2>Atualizar dimensões</h2>";
  if (!$arenas) echo "<p class='muted'>(cria primeiro um Campo)</p>";
  echo "<form method='post' style='max-width:600px'>
    <input type='hidden' name='update_map_dimensions' value='1'>
    <div class='form-group'><label for='arena_id_dim'>Campo</label><select id='arena_id_dim' name='arena_id' required>";
  foreach ($arenas as $a) {
    echo "<option value='{$a['id']}'>".htmlspecialchars($a['name'])."</option>";
  }
  echo "</select></div>
    <div class='form-group'><label for='floor_dim'>Piso</label><input id='floor_dim' type='number' name='floor' required></div>
    <div class='form-group'><label for='width_dim'>Largura (metros)</label><input id='width_dim' type='number' step='0.01' name='width' placeholder='Opcional'></div>
    <div class='form-group'><label for='height_dim'>Altura (metros)</label><input id='height_dim' type='number' step='0.01' name='height' placeholder='Opcional'></div>
    <button type='submit' class='btn'>Guardar dimensões</button>
  </form></section>";

  echo "<section class='card' style='margin-top:1.5rem;'><p class='muted'>Carrega mapas por piso para cada arena e define as dimensões para posicionamento preciso dos beacons.</p></section>";
  page_footer(); exit;
}

if ($uri==='/owner/beacons') {
  $ownerId = $auth->requireOwner();
  $flash = $_SESSION['owner_beacons_flash'] ?? null;
  unset($_SESSION['owner_beacons_flash']);

  $parseOptionalFloat = static function (?string $val): ?float {
    if ($val === null) return null;
    $v = trim($val);
    if ($v === '') return null;
    return (float)$v;
  };

  if ($method==='POST' && isset($_POST['upsert_beacon'])) {
    $arenaId = (int)($_POST['arena_id'] ?? 0);
    $floor = (int)($_POST['floor'] ?? 0);
    $uuid = strtolower(trim((string)($_POST['uuid'] ?? '')));
    $major = 0;
    $minor = 0;
    $txPower = -59;
    $label = trim((string)($_POST['label'] ?? ''));
    $label = $label === '' ? null : $label;
    $x = $parseOptionalFloat($_POST['x'] ?? null);
    $y = $parseOptionalFloat($_POST['y'] ?? null);

    try {
      if ($arenaId<=0) {
        throw new RuntimeException('Campo inválido.');
      }
      if ($floor < 0) {
        throw new RuntimeException('Piso inválido.');
      }
      if ($uuid === '') {
        throw new RuntimeException('UUID inválido.');
      }
      if ($x === null || $y === null) {
        throw new RuntimeException('Define as coordenadas X e Y.');
      }
      $thisArena = null;
      foreach ($repo->listArenasByOwner($ownerId) as $a) {
        if ((int)$a['id']===$arenaId) { $thisArena=$a; break; }
      }
      if (!$thisArena) {
        throw new RuntimeException('Não tens acesso a esse campo.');
      }
      $repo->upsertBeacon($arenaId, $uuid, $major, $minor, $floor, $txPower, $label, $x, $y);
      $_SESSION['owner_beacons_flash'] = ['type'=>'success','msg'=>'Beacon guardado.'];
    } catch (Throwable $ex) {
      $_SESSION['owner_beacons_flash'] = ['type'=>'error','msg'=>$ex->getMessage()];
    }
    header('Location: /owner/beacons'); exit;
  }

  $arenas = $repo->listArenasByOwner($ownerId);
  $mapsByArena = [];
  $beaconsByArena = [];
  foreach ($arenas as $a) {
    $mapRows = $repo->listMapsByArena((int)$a['id']);
    if ($mapRows) {
      $mapsByArena[(int)$a['id']] = $mapRows;
    }
    $rows = $repo->findBeaconsByArena((int)$a['id']);
    if ($rows) {
      $beaconsByArena[(int)$a['id']] = ['name'=>$a['name'],'rows'=>$rows];
    }
  }

  page_header("Beacons");
  echo "<section class='card'><h2>Adicionar / Atualizar beacon</h2>";
  if ($flash) {
    $cls = $flash['type']==='success' ? "alert success" : "alert";
    $msg = htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8');
    echo "<div class='$cls'>$msg</div>";
  }
  if (!$arenas) echo "<p class='muted'>(cria primeiro um Campo)</p>";
  $mapsJson = htmlspecialchars(json_encode($mapsByArena, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
  echo "<form method='post' style='max-width:680px' data-maps='$mapsJson'>
    <input type='hidden' name='upsert_beacon' value='1'>
    <div class='form-group'><label for='arena_id_beacon'>Campo</label><select id='arena_id_beacon' name='arena_id' required>";
  foreach ($arenas as $a) {
    echo "<option value='{$a['id']}'>".htmlspecialchars($a['name'])."</option>";
  }
  echo "</select></div>
    <div class='form-group'><label for='floor_beacon'>Piso</label><input id='floor_beacon' type='number' name='floor' required></div>
    <div class='form-group'><label for='uuid'>UUID</label><input id='uuid' name='uuid' placeholder='xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' required></div>
    <div class='form-group'><label for='x'>X (metros)</label><input id='x' type='number' step='0.01' name='x' required></div>
    <div class='form-group'><label for='y'>Y (metros)</label><input id='y' type='number' step='0.01' name='y' required></div>
    <div class='form-group'><label for='label'>Etiqueta (opcional)</label><input id='label' name='label' placeholder='Entrada Norte'></div>
    <button type='submit' class='btn'>Guardar beacon</button>
  </form></section>";

  echo "<section class='card' style='margin-top:1.5rem;'>
    <h2>Selecionar no mapa</h2>
    <p class='muted'>Escolhe o piso e clica no mapa para preencher X/Y. As dimensoes devem estar definidas em Mapas.</p>
    <div class='map-picker'>
      <div class='map-picker-toolbar'>
        <span id='map-picker-status' class='muted'>Seleciona um piso com mapa.</span>
      </div>
      <div class='map-picker-canvas'>
        <img id='map-picker-image' alt='Mapa do piso' />
        <div id='map-picker-marker' aria-hidden='true'></div>
      </div>
    </div>
  </section>";

  echo "<script>
  (function(){
    var form = document.querySelector('form[data-maps]');
    if (!form) return;
    var mapsByArena = {};
    try { mapsByArena = JSON.parse(form.getAttribute('data-maps') || '{}'); } catch (e) {}

    var arenaSel = document.getElementById('arena_id_beacon');
    var floorInput = document.getElementById('floor_beacon');
    var img = document.getElementById('map-picker-image');
    var marker = document.getElementById('map-picker-marker');
    var status = document.getElementById('map-picker-status');
    var xInput = document.getElementById('x');
    var yInput = document.getElementById('y');
    var currentMap = null;

    function findMap(arenaId, floor) {
      var list = mapsByArena[String(arenaId)] || [];
      for (var i=0; i<list.length; i++) {
        if (Number(list[i].floor) === Number(floor)) return list[i];
      }
      return null;
    }

    function updateMap() {
      var arenaId = arenaSel.value;
      var floor = floorInput.value;
      currentMap = findMap(arenaId, floor);
      marker.style.display = 'none';
      if (!currentMap) {
        img.removeAttribute('src');
        status.textContent = 'Sem mapa para este piso.';
        return;
      }
      if (!currentMap.width || !currentMap.height) {
        img.src = currentMap.map_url;
        status.textContent = 'Define a largura/altura deste mapa em Mapas.';
        return;
      }
      img.src = currentMap.map_url;
      status.textContent = 'Clica no mapa para definir X/Y.';
    }

    function positionMarker(px, py) {
      marker.style.left = px + 'px';
      marker.style.top = py + 'px';
      marker.style.display = 'block';
    }

    function handleClick(ev) {
      if (!currentMap || !currentMap.width || !currentMap.height) return;
      var rect = img.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      var x = ev.clientX - rect.left;
      var y = ev.clientY - rect.top;
      if (x < 0 || y < 0 || x > rect.width || y > rect.height) return;
      var metersX = (x / rect.width) * Number(currentMap.width);
      var metersY = (y / rect.height) * Number(currentMap.height);
      xInput.value = metersX.toFixed(2);
      yInput.value = metersY.toFixed(2);
      positionMarker(x, y);
    }

    arenaSel.addEventListener('change', updateMap);
    floorInput.addEventListener('input', updateMap);
    img.addEventListener('click', handleClick);
    updateMap();
  })();
  </script>";

  if ($beaconsByArena) {
    echo "<section class='card' style='margin-top:1.5rem;'><h2>Beacons registados</h2>";
    foreach ($beaconsByArena as $aid => $bundle) {
      $aName = htmlspecialchars($bundle['name']);
      echo "<h3>$aName</h3>";
      echo "<table class='table'><thead><tr><th>Piso</th><th>UUID</th><th>X</th><th>Y</th><th>Etiqueta</th></tr></thead><tbody>";
      foreach ($bundle['rows'] as $row) {
        $floor = (int)$row['floor'];
        $uuid = htmlspecialchars($row['uuid']);
        $xVal = $row['x'] !== null ? htmlspecialchars((string)$row['x']) : '—';
        $yVal = $row['y'] !== null ? htmlspecialchars((string)$row['y']) : '—';
        $label = $row['label'] ? htmlspecialchars($row['label']) : '—';
        echo "<tr><td>$floor</td><td>$uuid</td><td>$xVal</td><td>$yVal</td><td>$label</td></tr>";
      }
      echo "</tbody></table>";
    }
    echo "</section>";
  }

  echo "<section class='card' style='margin-top:1.5rem;'><p class='muted'>Define as coordenadas X/Y em metros, compatíveis com as dimensões do mapa para cada piso.</p></section>";
  page_footer(); exit;
}
/* ---------- HOME ---------- */
$year = date('Y');
echo <<<HTML
<!doctype html>
<html lang='pt'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>StrikeZone Central</title>
  <link rel='preconnect' href='https://fonts.googleapis.com'>
  <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
  <link href='https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap' rel='stylesheet'>
  <link href='https://fonts.googleapis.com/css2?family=JetBrains+Mono&display=swap' rel='stylesheet'>
  <style>
    :root {
      color-scheme: dark;
      --bg:#0b0d26;
      --panel:#161942;
      --panel-strong:#1e2256;
      --accent:#f64b9b;
      --accent-soft:#9f7eff;
      --text:#f4f6ff;
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      min-height:100vh;
      background:radial-gradient(circle at top, #1d2363 0%, #090a1f 55%);
      font-family:'Space Grotesk', system-ui, sans-serif;
      color:var(--text);
    }
    main {
      max-width:1200px;
      margin:0 auto;
      padding:3rem 1.5rem 4rem;
    }
    header.hero {
      display:grid;
      gap:3rem;
      align-items:center;
      grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    }
    .hero h1 { font-size:2.5rem; margin-bottom:1rem; }
    .hero p { color:rgba(244,246,255,0.8); line-height:1.6; }
    .hero-actions { display:flex; flex-wrap:wrap; gap:0.85rem; margin-top:1.5rem; }
    .btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:0.95rem 1.7rem;
      border-radius:999px;
      font-weight:600;
      font-size:1rem;
      text-decoration:none;
      transition:transform 0.2s, box-shadow 0.2s;
    }
    .btn-primary {
      background:linear-gradient(135deg,var(--accent),var(--accent-soft));
      color:#fff;
      box-shadow:0 18px 35px rgba(246,75,155,0.3);
    }
    .btn-secondary {
      color:#fff;
      border:1px solid rgba(255,255,255,0.2);
      background:rgba(255,255,255,0.05);
    }
    .btn:hover { transform:translateY(-1px); }
    .media-stack {
      display:grid;
      gap:1rem;
    }
    .media-stack img {
      width:100%;
      border-radius:20px;
      border:1px solid rgba(255,255,255,0.08);
      box-shadow:0 20px 45px rgba(5,6,19,0.6);
      object-fit:cover;
    }
    .feature-grid {
      margin-top:4rem;
      display:grid;
      gap:1.5rem;
      grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    }
    .feature-card {
      background:rgba(22,25,66,0.95);
      border:1px solid rgba(255,255,255,0.05);
      border-radius:22px;
      box-shadow:0 25px 45px rgba(4,5,17,0.55);
      overflow:hidden;
      display:flex;
      flex-direction:column;
    }
    .feature-card img { width:100%; height:180px; object-fit:cover; }
    .feature-card .content { padding:1.4rem; }
    .muted { color:rgba(244,246,255,0.7); }
    section { margin-top:4rem; }
    .steps {
      background:rgba(13,15,37,0.85);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:20px;
      padding:2rem;
      line-height:1.7;
      box-shadow:0 30px 60px rgba(5,6,19,0.5);
    }
    .api-card {
      background:#050513;
      border-radius:20px;
      padding:1.5rem;
      border:1px solid rgba(255,255,255,0.08);
      box-shadow:0 25px 45px rgba(0,0,0,0.55);
    }
    .api-card pre {
      margin:0;
      color:#f5f8ff;
      font-family:'JetBrains Mono', monospace;
      font-size:0.95rem;
      white-space:pre-wrap;
    }
    footer {
      text-align:center;
      margin-top:4rem;
      color:rgba(244,246,255,0.6);
      font-size:0.9rem;
    }
  </style>
</head>
<body>
  <main>
    <header class='hero'>
      <div>
        <img src='/assets/logo_strikezone.png' alt='StrikeZone' style='width:160px;margin-bottom:1.5rem;'>
        <h1>Plataforma central para jogos de airsoft</h1>
        <p>Localização indoor por beacons BLE, streaming em tempo real e gestão de partidas — tudo numa única consola criada para donos de campo.</p>
        <div class='hero-actions'>
          <a class='btn btn-primary' href='/owner/login'>Dono de Campo</a>
          <a class='btn btn-secondary' href='/register'>Criar Conta</a>
          <a class='btn btn-secondary' href='#api'>Ver API</a>
        </div>
      </div>
      <div class='media-stack'>
        <img src='/assets/FotoEquipa.jpeg' alt='Equipa StrikeZone'>
 
      </div>
    </header>

    <section class='feature-grid'>
      <article class='feature-card'>
        <img src='/assets/BLE.jpeg' alt='Localização indoor'>
        <div class='content'>
          <h3>Localização Indoor</h3>
          <p class='muted'>Integração com beacons BLE para determinar o piso de cada jogador com heurística e confiança ajustável.</p>
        </div>
      </article>
      <article class='feature-card'>
        <img src='/assets/GestãodeEquipas.png' alt='Gestão de Partidas'>
        <div class='content'>
          <h3>Gestão de Partidas</h3>
          <p class='muted'>Cria arenas, equipas e códigos de entrada. Mantém o roster sincronizado e regista jogadores em segundos.</p>
        </div>
      </article>
      <article class='feature-card'>
        <img src='/assets/Taticas.png' alt='Tempo Real'>
        <div class='content'>
          <h3>Tempo Real</h3>
          <p class='muted'>Publicação via Redis/Memurai e consumo em Server-Sent Events para dashboards ao vivo sem complicações.</p>
        </div>
      </article>
    </section>

    <section>
      <h2>Como funciona</h2>
      <div class='steps'>
        <ol>
          <li>Jogadores enviam scans BLE (UUID, major, minor e RSSI) para a API.</li>
          <li>O servidor mapeia cada beacon para um piso e aplica histerese para estabilizar a posição.</li>
          <li>O estado permanece guardado em base de dados e é publicado em canais de equipa e jogo.</li>
          <li>Dashboards consomem os eventos SSE e atualizam a visualização em tempo real.</li>
        </ol>
      </div>
    </section>

    <section id='api'>
      <h2>API (resumo)</h2>
      <div class='api-card'>
        <pre>POST /api/register               {email,password,display_name}
POST /api/login                  {email,password}
POST /api/arena/create           (Bearer)
GET  /api/arena/list             (Bearer)
POST /api/match/create           (Bearer)
GET  /api/match/list?arena_id    (Bearer)
POST /api/match/join             (Bearer)
POST /api/match/register-player  (Bearer)
GET  /api/match/roster?match_id  (Bearer)
GET  /api/maps?match_id|arena_id {maps:[{floor,map_url,width,height,beacons}]}
POST /api/scan                   (Bearer)</pre>
      </div>
    </section>

    <footer>© {$year} StrikeZone Central</footer>
  </main>
</body>
</html>
HTML;
