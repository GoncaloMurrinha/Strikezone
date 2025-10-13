<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Repository.php';
require __DIR__ . '/../src/FloorEngine.php';
require __DIR__ . '/../src/Jwt.php';
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
if ($uri==='/api/register' && $method==='POST') { $api->register(); exit; }
if ($uri==='/api/login'    && $method==='POST') { $api->login(); exit; }

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
  echo "<title>".htmlspecialchars($title)."</title><link rel='stylesheet' href='/assets/style.css'></head><body><div class='wrap'>";
  echo "<h1>$title</h1><p><a href='/owner'>Dashboard</a> | <a href='/owner/maps'>Mapas</a> | <a href='/owner/logout'>Sair</a></p><hr>";
}
function page_footer(){ echo "</div></body></html>"; }

if ($uri==='/owner/login') {
  if ($method==='POST') {
    $ok = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok) { header('Location: /owner'); exit; }
    $err = "Credenciais inválidas";
  }
  echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><link rel='stylesheet' href='/assets/style.css'><title>Login — Dono</title></head><body class='wrap'>";
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

if ($uri==='/owner') {
  $ownerId = $auth->requireOwner();
  if ($method==='POST' && isset($_POST['create_arena'])) {
    $name = trim($_POST['arena_name'] ?? '');
    if ($name!=='') $repo->createArena($ownerId,$name);
    header('Location: /owner'); exit;
  }
  $arenas = $repo->listArenasByOwner($ownerId);
  page_header("Painel do Campo");
  echo "<h2>Os meus Campos</h2>";
  if (!$arenas) echo "<p>(ainda sem campos)</p>";
  echo "<ul>";
  foreach ($arenas as $a) {
    echo "<li><b>".htmlspecialchars($a['name'])."</b> — <a href='/owner/arena/".$a['id']."'>abrir</a></li>";
  }
  echo "</ul>";
  echo "<h3>Novo Campo</h3>
    <form method='post'>
      <input type='hidden' name='create_arena' value='1'>
      <label>Nome do Campo <input name='arena_name' required></label>
      <button type='submit'>Criar</button>
    </form>";
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
  echo "<p><a href='/owner'>&larr; voltar</a></p>";
  echo "<h2>Jogos</h2>";
  if (!$matches) echo "<p>(ainda sem jogos)</p>";
  echo "<table class='tbl'><thead><tr><th>ID</th><th>Nome</th><th>Início</th><th>Equipas</th><th>Códigos</th><th></th></tr></thead><tbody>";
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
    <form method='post' class='form-grid'>
      <input type='hidden' name='create_match' value='1'>
      <label>Nome <input name='name' required></label>
      <label>Início (YYYY-MM-DD HH:MM:SS) <input name='starts_at' required></label>
      <label>Equipa A <input name='team_a' value='Azuis'></label>
      <label>Equipa B <input name='team_b' value='Vermelhos'></label>
      <button type='submit'>Criar</button>
    </form>";

  echo "<h3>Mapas por piso</h3>";
  if ($maps){
    echo "<ul>";
    foreach ($maps as $mp){
      echo "<li>Piso ".(int)$mp['floor']." — <a href='".htmlspecialchars($mp['map_url'])."' target='_blank'>abrir</a></li>";
    }
    echo "</ul>";
  } else {
    echo "<p>(Sem mapas ainda, carrega-os em <a href='/owner/maps'>Mapas</a>)</p>";
  }

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
  $maps = $repo->listMapsByArena((int)$match['arena_id']);

  page_header("Ao vivo — Match #$matchId");
  echo "<p><a href='/owner/arena/{$match['arena_id']}'>&larr; voltar</a></p>";
  echo "<h2>".htmlspecialchars($match['name'])."</h2>";
  echo "<p><b>A:</b> ".htmlspecialchars($match['team_a_name'])." &nbsp; <b>B:</b> ".htmlspecialchars($match['team_b_name'])."</p>";
  echo "<p>Códigos — A=<code>{$match['team_a_code']}</code> &nbsp; B=<code>{$match['team_b_code']}</code></p>";

  echo "<div id='live' style='display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start'>";
  echo "<section><h3>Equipa A</h3><ul id='teamA'></ul></section>";
  echo "<section><h3>Equipa B</h3><ul id='teamB'></ul></section>";
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

  page_footer(); exit;
}

if ($uri==='/owner/maps') {
  $ownerId = $auth->requireOwner();
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
  echo "<form method='post' enctype='multipart/form-data' class='form-grid'>
    <input type='hidden' name='upload_map' value='1'>
    <label>Campo <select name='arena_id' required>";
  foreach ($arenas as $a) echo "<option value='{$a['id']}'>".htmlspecialchars($a['name'])."</option>";
  echo "</select></label>
    <label>Piso <input type='number' name='floor' required></label>
    <label>Ficheiro (png/jpg/webp/svg, max ".(int)$config['uploads']['max_mb']."MB) <input type='file' name='mapfile' required></label>
    <button type='submit'>Upload</button>
  </form>";
  page_footer(); exit;
}

/* ---------- HOME ---------- */
echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><link rel='stylesheet' href='/assets/style.css'><title>StrikeZone Central</title></head><body class='wrap'>";
echo "<h1>StrikeZone — Central</h1>
<p>API & Dashboard. Entra como <a href='/owner/login'>Dono do Campo</a>.</p>
<pre>
POST /api/register   {email,password,display_name}
POST /api/login      {email,password}
POST /api/arena/create  (Bearer)
GET  /api/arena/list    (Bearer)
POST /api/match/create  (Bearer)
GET  /api/match/list?arena_id=...  (Bearer)
POST /api/match/join    (Bearer)
GET  /api/match/roster?match_id=... (Bearer)
POST /api/scan          (Bearer)
</pre>";
echo "</body></html>";
