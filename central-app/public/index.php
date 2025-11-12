<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
  $fullPath = realpath(__DIR__ . $path);
  $publicDir = realpath(__DIR__);
  if ($path !== '/' && $fullPath && str_starts_with($fullPath, $publicDir) && is_file($fullPath)) {
    return false;
  }
}

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Repository.php';
require __DIR__ . '/../src/FloorEngine.php';
require __DIR__ . '/../src/Jwt.php';
require __DIR__ . '/../src/MiniRedis.php';
require __DIR__ . '/../src/Realtime.php';
require __DIR__ . '/../src/ApiController.php';
require __DIR__ . '/../src/OwnerAuth.php';

$config = require __DIR__ . '/../src/config.php';
$pdo = (new DB($config['db']))->pdo();
$repo = new Repository($pdo);
$fe = new FloorEngine();
$rt = new Realtime($config['redis']);
$api = new ApiController($repo,$fe,$rt,$config);
$auth = new OwnerAuth($repo);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- API ---------- */
if ($uri === '/api/register.php') {require __DIR__.'/api/register.php'; exit;}
if ($uri==='/api/login'    && $method==='POST') { $api->login(); exit; }
if ($uri==='/api/code/resolve' && ($method==='GET' || $method==='POST')) { $api->codeResolve(); exit; }
if ($uri==='/api/code/resolve' && ($method==='GET' || $method==='POST')) { $api->codeResolve(); exit; }
if ($uri === '/register') { readfile(__DIR__ . '/register.html'); exit; }

if ($uri==='/api/arena/create' && $method==='POST') { $api->arenaCreate(); exit; }
if ($uri==='/api/arena/list'   && $method==='GET')  { $api->arenaList(); exit; }

if ($uri==='/api/match/create' && $method==='POST') { $api->matchCreate(); exit; }
if ($uri==='/api/match/list'   && $method==='GET')  { $api->matchList(); exit; }
if ($uri==='/api/match/join'   && $method==='POST') { $api->matchJoin(); exit; }
if ($uri==='/api/match/roster' && $method==='GET')  { $api->matchRoster(); exit; }

if ($uri==='/api/scan' && $method==='POST') { $api->submitScan(); exit; }

/* ---------- OWNER UI ---------- */
function page_header($title='Owner'){
  echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
  echo "<title>".htmlspecialchars($title)."</title><link rel='stylesheet' href='/assets/style.css'></head><body data-bs-theme='dark' class='owner-ui bg-dark text-light'>";
  echo "<header class='owner-navbar'><div class='owner-nav-inner'>";
  echo "  <div class='brand'>Strikezone</div>";
  echo "  <nav class='owner-nav-links'>";
  echo "    <a href='/owner'>Dashboard</a>";
  echo "    <a href='/owner/maps'>Mapas</a>";
  echo "    <a href='/owner/logout'>Sair</a>";
  echo "  </nav>";
  echo "</div></header>";
  echo "<div class='wrap'><h1 class='page-title h3'>".htmlspecialchars($title)."</h1>";
}
function page_footer(){ echo "</div><script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script></body></html>"; }

if ($uri==='/owner/login') {
  if ($method==='POST') {
    $ok = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok) { header('Location: /owner'); exit; }
    $err = "Credenciais inválidas";
  }
  echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'><link rel='stylesheet' href='/assets/style.css'><title>Login — Dono</title></head><body data-bs-theme='dark' class='bg-dark text-light wrap'>";
  echo "<h1>Login — Dono do Campo</h1>";
  if (!empty($err)) echo "<p class='err'>$err</p>";
  echo "<form method='post' class='form-grid' style='max-width:420px'>
    <label>Email <input name='email' type='email' required></label>
    <label>Password <input name='password' type='password' required></label>
    <button type='submit'>Entrar</button>
  </form><p><a href='/'>Voltar</a></p></body></html>";
  exit;
}

if ($uri==='/owner/logout') { $auth->logout(); header('Location: /owner/login'); exit; }

// Owner match kill switch endpoints
if ($uri==='/owner/match/stop' && $method==='POST') {
  $ownerId = $auth->requireOwner();
  if (session_status()===PHP_SESSION_ACTIVE) { @session_write_close(); }
  $mid = (int)($_POST['match_id'] ?? 0);
  $match = $repo->getMatchById($mid);
  if ($mid<=0 || !$match) { http_response_code(422); exit('invalid_match'); }
  $ok=false; foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===(int)$match['arena_id']) $ok=true;
  if (!$ok){ http_response_code(403); exit('forbidden'); }
  $mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
  $stopKey = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$mid:stopped";
  $mr->set($stopKey,'1', 86400);
  // publish immediate stop to match + team channels so SSE exits at once
  $prefix = ($config['redis']['prefix'] ?? 'airsoft:');
  $mr->publish($prefix."match:$mid", json_encode(['ctrl'=>'stop']));
  $mr->publish($prefix."team:".($mid*10+1), json_encode(['ctrl'=>'stop']));
  $mr->publish($prefix."team:".($mid*10+2), json_encode(['ctrl'=>'stop']));
  header('Location: /owner/match/'.$mid); exit;
}
if ($uri==='/owner/match/start' && $method==='POST') {
  $ownerId = $auth->requireOwner();
  if (session_status()===PHP_SESSION_ACTIVE) { @session_write_close(); }
  $mid = (int)($_POST['match_id'] ?? 0);
  $match = $repo->getMatchById($mid);
  if ($mid<=0 || !$match) { http_response_code(422); exit('invalid_match'); }
  $ok=false; foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===(int)$match['arena_id']) $ok=true;
  if (!$ok){ http_response_code(403); exit('forbidden'); }
  $mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
  $stopKey = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$mid:stopped";
  $mr->del($stopKey);
  header('Location: /owner/match/'.$mid); exit;
}

if ($uri==='/owner') {
  $ownerId = $auth->requireOwner();
  if (session_status()===PHP_SESSION_ACTIVE) { @session_write_close(); }
  if ($method==='POST' && isset($_POST['create_arena'])) {
    $name = trim($_POST['arena_name'] ?? '');
    if ($name!=='') $repo->createArena($ownerId,$name);
    header('Location: /owner'); exit;
  }
  $arenas = $repo->listArenasByOwner($ownerId);
  page_header("Painel do Campo");

  echo "<div class='dashboard-layout'>";

  // Left: arenas list card
  echo "  <section class='dash-card'>";
  echo "    <div class='dash-card-header'><h2 class='h5 m-0'>Os meus Campos".(count($arenas)?" <span class='count-badge'>".count($arenas)."</span>":"")."</h2></div>";
  echo "    <div class='dash-card-body'>";
  if (!$arenas) {
    echo "<p class='empty muted'>(ainda sem campos)</p>";
  }
  echo "      <ul class='list-group modern-list'>";
  foreach ($arenas as $a) {
    echo "<li class='list-group-item bg-transparent text-light d-flex justify-content-between align-items-center'>".
         "<span class='arena-name'>".htmlspecialchars($a['name'])."</span>".
         "<a class='btn btn-sm btn-outline-light' href='/owner/arena/".$a['id']."'>Abrir</a>".
         "</li>";
  }
  echo "      </ul>";
  echo "    </div>";
  echo "  </section>";

  // Right: new arena card
  echo "  <section class='dash-card'>";
  echo "    <div class='dash-card-header'><h2 class='h5 m-0'>Novo Campo</h2></div>";
  echo "    <div class='dash-card-body'>";
  echo "      <form method='post' class='row g-3'>";
  echo "        <input type='hidden' name='create_arena' value='1'>";
  echo "        <div class='col-12'>";
  echo "          <label class='form-label'>Nome do Campo</label>";
  echo "          <input class='form-control form-control-lg' name='arena_name' required placeholder='Ex.: Strikezone Norte'>";
  echo "        </div>";
  echo "        <div class='col-12'>";
  echo "          <button type='submit' class='btn btn-success btn-lg w-100'>Criar campo</button>";
  echo "        </div>";
  echo "      </form>";
  echo "    </div>";
  echo "  </section>";

  echo "</div>"; // dashboard-layout
  page_footer(); exit;
}

if (preg_match('#^/owner/arena/(\d+)$#', $uri, $m)) {
  $ownerId = $auth->requireOwner();
  if (session_status()===PHP_SESSION_ACTIVE) { @session_write_close(); }
  $arenaId = (int)$m[1];
  $ok=false; foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===$arenaId) $ok=true;
  if (!$ok){ http_response_code(403); exit('forbidden'); }

  if ($method==='POST' && isset($_POST['create_match'])) {
    $name = trim($_POST['name'] ?? '');
    $start= trim($_POST['starts_at'] ?? '');
    $ta   = trim($_POST['team_a'] ?? 'Azuis');
    $tb   = trim($_POST['team_b'] ?? 'Vermelhos');
    if ($name && $start){
      $codeA = ApiController::randomCode(6);
      $codeB = ApiController::randomCode(6);
      $mid = $repo->createMatch($arenaId,$name,$start,$ta,$tb,$codeA,$codeB);
      header("Location: /owner/match/$mid"); exit;
    }
  }

  $matches = $repo->listMatchesByArena($arenaId);
  $maps = $repo->listMapsByArena($arenaId);

  page_header("Campo #$arenaId");
  echo "<p><a class='link-light' href='/owner'>&larr; voltar</a></p>";
  echo "<h2>Jogos</h2>";
  if (!$matches) echo "<p>(ainda sem jogos)</p>";
  echo "<table class='table table-dark table-striped table-hover table-sm'><thead><tr><th>ID</th><th>Nome</th><th>Início</th><th>Equipas</th><th>Códigos</th><th></th></tr></thead><tbody>";
  foreach ($matches as $mrow) {
    echo "<tr>
      <td>{$mrow['id']}</td>
      <td>".htmlspecialchars($mrow['name'])."</td>
      <td>".htmlspecialchars($mrow['starts_at'])."</td>
      <td>".htmlspecialchars($mrow['team_a_name'])." vs ".htmlspecialchars($mrow['team_b_name'])."</td>
      <td>A=<code>{$mrow['team_a_code']}</code> &nbsp; B=<code>{$mrow['team_b_code']}</code></td>
      <td><a href='/owner/match/{$mrow['id']}'>ver ao vivo</a></td>
    </tr>";
  }
  echo "</tbody></table>";

  echo "<h3>Novo Jogo</h3>
    <form method='post' class='row g-3'>
      <input type='hidden' name='create_match' value='1'>
      <div class='col-md-6'><label class='form-label'>Nome</label><input class='form-control' name='name' required></div>
      <div class='col-md-6'><label class='form-label'>Início (YYYY-MM-DD HH:MM:SS)</label><input class='form-control' name='starts_at' required></div>
      <div class='col-md-6'><label class='form-label'>Equipa A</label><input class='form-control' name='team_a' value='Azuis'></div>
      <div class='col-md-6'><label class='form-label'>Equipa B</label><input class='form-control' name='team_b' value='Vermelhos'></div>
      <div class='col-12'><button type='submit' class='btn btn-success'>Criar</button></div>
    </form>";

  echo "<h3>Mapas por piso</h3>";
  if ($maps){
    echo "<ul class='list-group'>";
    foreach ($maps as $mp){
      echo "<li class='list-group-item bg-transparent text-light'>Piso ".(int)$mp['floor']." — <a class='link-light' href='".htmlspecialchars($mp['map_url'])."' target='_blank'>abrir</a></li>";
    }
    echo "</ul>";
  } else {
    echo "<p>(Sem mapas ainda, carrega-os em <a href='/owner/maps'>Mapas</a>)</p>";
  }

  page_footer(); exit;
}

if (preg_match('#^/owner/match/(\d+)$#', $uri, $m)) {
  $ownerId = $auth->requireOwner();
  if (session_status()===PHP_SESSION_ACTIVE) { @session_write_close(); }
  $matchId = (int)$m[1];
  $match = $repo->getMatchById($matchId);
  if (!$match){ http_response_code(404); exit('not found'); }
  $ok=false; foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===(int)$match['arena_id']) $ok=true;
  if (!$ok){ http_response_code(403); exit('forbidden'); }

  $members = $repo->listMembersByMatch($matchId);
  $maps = $repo->listMapsByArena((int)$match['arena_id']);
  // estado via kill switch
  $mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
  $stopKey = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$matchId:stopped";
  $isStopped = ($mr->get($stopKey)!==null);

  page_header("Ao vivo — Match #$matchId");
  echo "<p><a class='link-light' href='/owner/arena/{$match['arena_id']}'>&larr; voltar</a></p>";
  echo "<h2>".htmlspecialchars($match['name'])."</h2>";
  echo "<p><b>A:</b> ".htmlspecialchars($match['team_a_name'])." &nbsp; <b>B:</b> ".htmlspecialchars($match['team_b_name'])."</p>";
  echo "<p>Códigos — A=<code>{$match['team_a_code']}</code> &nbsp; B=<code>{$match['team_b_code']}</code></p>";

  echo "<div class='d-flex align-items-center gap-2 mb-3'>";
  echo "  <span id='matchStatus' class='badge ".($isStopped?"text-bg-secondary":"text-bg-success")."'>Estado: ".($isStopped?"Parado":"Ao vivo")."</span>";
  echo "  <form id='stopForm' method='post' action='/owner/match/stop' class='d-inline'><input type='hidden' name='match_id' value='".$matchId."'><button id='btnStop' class='btn btn-outline-warning btn-sm'".($isStopped?" disabled":"").">Parar partida</button></form>";
  echo "  <form id='startForm' method='post' action='/owner/match/start' class='d-inline'><input type='hidden' name='match_id' value='".$matchId."'><button id='btnStart' class='btn btn-outline-success btn-sm'".(!$isStopped?" disabled":"").">Iniciar partida</button></form>";
  echo "</div>";
  echo "<div id='live' class='row g-3 align-items-start'>";
  echo "<section class='col-md-6'><h3 class='h5'>Equipa A</h3><ul id='teamA' class='list-group'></ul></section>";
  echo "<section class='col-md-6'><h3 class='h5'>Equipa B</h3><ul id='teamB' class='list-group'></ul></section>";
  echo "</div>";

  if ($maps){
    echo "<h3>Mapas por piso</h3><ul>";
    foreach ($maps as $mp){
      echo "<li>Piso ".(int)$mp['floor']." — <a href='".htmlspecialchars($mp['map_url'])."' target='_blank'>ver</a></li>";
    }
    echo "</ul>";
  }

  echo "<script>
    const matchId = $matchId;
    const A = document.getElementById('teamA');
    const B = document.getElementById('teamB');
    const state = {};
    const es = new EventSource('/stream_match.php?match_id='+matchId);
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
    es.addEventListener('pos', ev=>{
      try{
        const j = JSON.parse(ev.data);
        const side = (j.team_id % 10)===1 ? 'A' : 'B';
        state[j.user.id] = { name:j.user.name, floor:j.pos.floor, conf:j.pos.conf, side };
        render();
      }catch(e){}
    });
  </script>";
  echo "<script>\n    (function(){\n      var stopForm = document.getElementById('stopForm');\n      var startForm = document.getElementById('startForm');\n      var badge = document.getElementById('matchStatus');\n      var btnStop = document.getElementById('btnStop');\n      var btnStart = document.getElementById('btnStart');\n      function setBadge(text, cls){ badge.textContent = 'Estado: '+text; badge.className = 'badge '+cls; }\n      function disableBoth(dis){ if(btnStop) btnStop.disabled = dis; if(btnStart) btnStart.disabled = dis; }\n      function postForm(action){\n        var fd = new URLSearchParams();\n        fd.set('match_id', String(matchId));\n        return fetch(action, { method:'POST', body:fd, credentials:'same-origin', redirect:'follow' });\n      }\n      if (stopForm) stopForm.addEventListener('submit', function(ev){\n        ev.preventDefault();\n        disableBoth(true);\n        setBadge('A parar…', 'text-bg-warning');\n        try{ if (typeof es !== 'undefined' && es && es.close) es.close(); }catch(e){}\n        postForm(stopForm.action).finally(function(){\n          setBadge('Parado', 'text-bg-secondary');\n          disableBoth(false);\n          if(btnStop) btnStop.disabled = true;\n          if(btnStart) btnStart.disabled = false;\n        });\n      });\n      if (startForm) startForm.addEventListener('submit', function(ev){\n        ev.preventDefault();\n        disableBoth(true);\n        setBadge('A iniciar…', 'text-bg-info');\n        postForm(startForm.action).finally(function(){\n          setBadge('Ao vivo', 'text-bg-success');\n          disableBoth(false);\n          if(btnStop) btnStop.disabled = false;\n          if(btnStart) btnStart.disabled = true;\n        });\n      });\n    })();\n  </script>";

  page_footer(); exit;
}

if ($uri==='/owner/maps') {
  $ownerId = $auth->requireOwner();
  if (session_status()===PHP_SESSION_ACTIVE) { @session_write_close(); }
  $cfgUp = $config['uploads'];
  if (!is_dir($cfgUp['maps_dir'])) @mkdir($cfgUp['maps_dir'], 0775, true);

  if ($method==='POST' && isset($_POST['upload_map'])) {
    $arenaId = (int)($_POST['arena_id'] ?? 0);
    $floor   = (int)($_POST['floor'] ?? 0);
    if ($arenaId>0 && !empty($_FILES['mapfile']['name'])) {
      $f = $_FILES['mapfile'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp','svg'])) die('extensão inválida');
        if ($f['size'] > $cfgUp['max_mb']*1024*1024) die('ficheiro grande demais');
        $name = 'map_a'.$arenaId.'_floor_'.$floor.'_'.time().'.'.$ext;
        $dest = rtrim($cfgUp['maps_dir'],'/\\').DIRECTORY_SEPARATOR.$name;
        move_uploaded_file($f['tmp_name'], $dest);
        $url  = rtrim($cfgUp['maps_url'],'/').'/'.$name;
        $thisArena = null;
        foreach ($repo->listArenasByOwner($ownerId) as $a) if ((int)$a['id']===$arenaId) $thisArena=$a;
        if (!$thisArena) die('forbidden arena');
        $repo->upsertMap($arenaId, $floor, 'Piso '.$floor, $url);
        header('Location: /owner/maps'); exit;
      }
    }
  }

  $arenas = $repo->listArenasByOwner($ownerId);
  page_header("Mapas do Campo");
  echo "<h2>Carregar mapa por piso</h2>";
  if (!$arenas) echo "<p>(cria primeiro um Campo)</p>";
  echo "<form method='post' enctype='multipart/form-data' class='row g-3' style='max-width:700px'>
    <input type='hidden' name='upload_map' value='1'>
    <div class='col-md-6'><label class='form-label'>Campo</label><select class='form-select' name='arena_id' required>";
  foreach ($arenas as $a) echo "<option value='{$a['id']}'>".htmlspecialchars($a['name'])."</option>";
  echo "</select></div>
    <div class='col-md-3'><label class='form-label'>Piso</label><input class='form-control' type='number' name='floor' required></div>
    <div class='col-md-9'><label class='form-label'>Ficheiro (png/jpg/webp/svg, max ".(int)$config['uploads']['max_mb']."MB)</label><input class='form-control' type='file' name='mapfile' required></div>
    <div class='col-12'><button type='submit' class='btn btn-primary'>Upload</button></div>
  </form>";
  page_footer(); exit;
}
/* ---------- HOME ---------- */
echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
echo "<title>StrikeZone Central</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link rel='stylesheet' href='/assets/style.css'>";
echo "</head><body data-bs-theme='dark' class='bg-dark text-light'>";

/* ---------- HERO ---------- */
echo "<section class='py-5 bg-black border-bottom border-secondary'>";
echo "  <div class='container'>";
echo "    <div class='row align-items-center g-4'>";
echo "      <div class='col-lg-6'>";
echo "        <h1 class='display-6 fw-semibold'>StrikeZone — Central</h1>";
echo "        <p class='lead text-secondary'>Plataforma central para jogos de airsoft com localização indoor por beacons, streaming em tempo real e gestão de partidas.</p>";
echo "        <div class='d-flex gap-2'>";
echo "          <a class='btn btn-primary btn-lg' href='/owner/login'>Entrar como Dono</a>";
echo "          <a class='btn btn-outline-light btn-lg' href='#api'>Ver API</a>";
echo "        </div>";
echo "      </div>";
echo "      <div class='col-lg-6 text-center'>";
echo "        <img class='img-fluid rounded border border-secondary shadow' alt='Jogadores de airsoft' src='/assets/FotoEquipa.jpeg'>";
echo "      </div>";
echo "    </div>";
echo "  </div>";
echo "</section>";

// Features
echo "<section class='py-5'>";
echo "  <div class='container'>";
echo "    <div class='row g-4'>";
echo "      <div class='col-md-4'>";
echo "        <div class='card h-100 bg-black border-secondary'>";
echo "          <img class='card-img-top' alt='Beacons BLE' src='/assets/BLE.jpeg'>";
echo "          <div class='card-body'>";
echo "            <h3 class='h5 card-title'>Localização Indoor</h3>";
echo "            <p class='card-text text-secondary'>Integração com beacons BLE para determinar o piso dos jogadores com heurística de confiança.</p>";
echo "          </div>";
echo "        </div>";
echo "      </div>";
echo "      <div class='col-md-4'>";
echo "        <div class='card h-100 bg-black border-secondary'>";
echo "          <img class='card-img-top' alt='Gestão de equipas' src='/assets/GestãodeEquipas.png'>";
echo "          <div class='card-body'>";
echo "            <h3 class='h5 card-title'>Gestão de Partidas</h3>";
echo "            <p class='card-text text-secondary'>Cria arenas, jogos e equipas com códigos de entrada. Acompanha o roster em tempo real.</p>";
echo "          </div>";
echo "        </div>";
echo "      </div>";
echo "      <div class='col-md-4'>";
echo "        <div class='card h-100 bg-black border-secondary'>";
echo "          <img class='card-img-top' alt='Streaming de dados' src='/assets/Taticas.png'>";
echo "          <div class='card-body'>";
echo "            <h3 class='h5 card-title'>Tempo Real</h3>";
echo "            <p class='card-text text-secondary'>Publicação via Redis/Memurai e consumo por Server‑Sent Events para dashboards ao vivo.</p>";
echo "          </div>";
echo "        </div>";
echo "      </div>";
echo "    </div>";
echo "  </div>";
echo "</section>";

// How it works
echo "<section class='py-5 border-top border-secondary'>";
echo "  <div class='container'>";
echo "    <h2 class='h4 mb-3'>Como Funciona</h2>";
echo "    <ol class='text-secondary'>";
echo "      <li>Jogadores enviam scans de beacons para a API com o RSSI observado.</li>";
echo "      <li>O servidor mapeia cada beacon para um piso da arena e decide o piso atual (histerese).</li>";
echo "      <li>O estado do jogador é persistido e a posição é publicada em canais Redis (equipa/jogo).</li>";
echo "      <li>O dashboard consome os eventos em SSE e atualiza a posição em tempo real.</li>";
echo "    </ol>";
echo "  </div>";
echo "</section>";

// API section
echo "<section id='api' class='py-5'>";
echo "  <div class='container'>";
echo "    <h2 class='h4 mb-3'>API (Resumo)</h2>";
echo "    <pre class='bg-black border border-secondary rounded p-3 mb-0'>
POST /api/register   {email,password,display_name}
POST /api/login      {email,password}
GET  /api/code/resolve?code=XXXXXX   or   POST /api/code/resolve {code}
POST /api/arena/create  (Bearer)
GET  /api/arena/list    (Bearer)
POST /api/match/create  (Bearer)
GET  /api/match/list?arena_id=...  (Bearer)
POST /api/match/join    (Bearer)
GET  /api/match/roster?match_id=... (Bearer)
POST /api/scan          (Bearer)
</pre>";
echo "  </div>";
echo "</section>";

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script></body></html>";





