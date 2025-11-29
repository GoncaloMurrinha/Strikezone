# 🧠 StrikeZone – Central App

## 📋 Descrição
O **StrikeZone Central App** é o sistema central que gere a comunicação entre os jogadores e os administradores dos campos de airsoft.  
Esta aplicação fornece:
- API central para registo e gestão de partidas;
- Dashboard para administradores de campo;
- Comunicação em tempo real (Redis/Memurai);
- Integração com beacons BLE para localização indoor.

---

## ⚙️ Pré-requisitos

Antes de começar, certifica-te de que tens instalados:

| Ferramenta | Descrição | Download |
|-------------|------------|-----------|
| **XAMPP** | Servidor Apache + PHP + MySQL | https://www.apachefriends.org |
| **Git** | Controlo de versão | https://git-scm.com/downloads |
| **Composer** | Gestor de dependências PHP | https://getcomposer.org/download/ |
| **Memurai** | Alternativa a Redis no Windows | https://www.memurai.com/download |
| **Homebrew** *(macOS)* | Gestor de pacotes para instalar PHP/Redis/MySQL | https://brew.sh |
| **Visual Studio Code** *(opcional)* | Editor recomendado | https://code.visualstudio.com |

---

## 🗂️ Estrutura de pastas

Strikezone/
│
├── central-app/
│ ├── public/ # Ficheiros acessíveis via navegador
│ │ ├── index.php
│ │ └── uploads/ # Diretório para ficheiros enviados
│ │
│ ├── src/ # Código-fonte PHP (controladores, utilitários)
│ ├── sql/ # Scripts de criação da base de dados
│ ├── vendor/ # Dependências Composer
│ └── .env (opcional) # Configuração de ambiente
│
└── README.md

---

## 🛠️ Passos de Instalação

### 1️⃣ Clonar o repositório

```bash
git clone https://github.com/Goncalo-Murrinha/Strikezone.git
cd Strikezone/central-app
```

### 2️⃣ Instalar dependências (Composer)

```bash
composer install
```

### 3️⃣ Criar base de dados a partir do schema

- Windows (XAMPP):
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -p < .\sql\schema.sql
```

- Linux/macOS:
```bash
mysql -u root -p < ./sql/schema.sql
```

### 4️⃣ Iniciar Redis/Memurai

- Windows (Memurai):
```powershell
Start-Service Memurai
```
- Linux/macOS (Redis):
```bash
redis-server
```

### 5️⃣ Arrancar o servidor PHP (dev)

```bash
php -S 0.0.0.0:8080 -t public
```

Abrir: http://localhost:8080

### 🍎 Guia rápido macOS

macOS não traz PHP/Composer nem Redis por defeito, por isso o fluxo recomendado é:

1. **Instalar o Homebrew**
   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
   eval "$(/opt/homebrew/bin/brew shellenv)"
   ```
2. **Instalar toolchain**
   ```bash
   brew install php composer redis mysql
   ```
   (Opcional: podes continuar a usar o XAMPP/MAMP para MySQL; neste caso anota o caminho do socket, ex.: `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`).
3. **Configurar variáveis do `.env`**
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=airsoft_central
   DB_USERNAME=root
   DB_PASSWORD=
   DB_SOCKET=/opt/homebrew/var/mysql/mysql.sock   # usa o caminho do XAMPP se for o caso
   QR_OUTPUT_DIR=/Users/<tu_user>/Strikezone/central-app/public/uploads/qrcodes
   QR_BASE_URL=/uploads/qrcodes
   QR_SIZE=220
   ```
4. **Arrancar serviços**
   - MySQL (Homebrew): `brew services start mysql`
   - Redis: `brew services start redis`
   - Se estiveres em XAMPP, usa o `Manager-OSX.app` ou `sudo /Applications/XAMPP/xamppfiles/xampp startmysql`.
5. **Importar schema**
   ```bash
   mysql -u root < sql/schema.sql
   ```
6. **Servidor PHP**
   ```bash
   php -S 0.0.0.0:8080 -t public
   ```

Com estes passos tens o stack completo a correr localmente no macOS (Monterey+ ou Apple Silicon). Caso prefiras Docker, podes criar um `docker-compose` com `mysql` e `redis` e apontar o `.env` para os containers.

## 🧪 Testes unitários

- Como correr:

  - `php central-app/test.php`

- O que é testado:
  - `FloorEngine` — lógica de decisão de piso e histerese.
  - `Jwt` — assinatura e verificação (roundtrip e falha com secret errado).
  - `helpers` — extração do token do header Authorization.
  - `ApiController::randomCode` — tamanho e charset.

- Como funciona o runner:
  - Framework minimalista em `central-app/tests/_framework.php` com `register_test` e asserts (`assert_eq`, `assert_true`, `assert_same`).
  - Os ficheiros `*Test.php` registam testes via `register_test('nome', fn(){ ... })`.
  - `central-app/test.php` carrega todos os `*Test.php` e executa-os, mostrando ✔/✘ e devolvendo código de saída 0/1.

## 🚀 Otimizações de performance

- Lookup de beacons em lote no endpoint `/api/scan` (evita N queries por leitura):
  - Implementado em `central-app/src/Repository.php` com `getBeaconFloorsMap()`.
  - Usado em `central-app/src/ApiController.php` dentro de `submitScan()`.
- Conexões PDO persistentes para reduzir overhead de reconexão:
  - Ativado em `central-app/src/config.php` via `PDO::ATTR_PERSISTENT => true`.
- Micro‑otimização no `FloorEngine` para evitar `array_sum` desnecessário.

Sugestão opcional (DB): adicionar índice em `beacons(arena_id)` para acelerar listagens por arena.

## 🧩 Dicas

- Configurações: `central-app/src/config.php` (DB, Redis/Memurai, uploads, JWT).
- Endpoints e UI: `central-app/public/index.php` (roteamento simples em PHP embutido).
- Quando crias um jogo no painel de dono és questionado se preferes distribuir os códigos em texto ou por QR code. Essa escolha fica guardada em `matches.code_display_mode` — se já tinhas a base criada antes desta atualização corre `ALTER TABLE matches ADD COLUMN code_display_mode ENUM('text','qr') NOT NULL DEFAULT 'text';`.
- Os QR codes são gerados uma única vez com a biblioteca [endroid/qr-code](https://github.com/endroid/qr-code) e guardados em `public/uploads/qrcodes/` como ficheiros PNG. Podes customizar a localização via `.env` (`QR_OUTPUT_DIR`, `QR_BASE_URL`, `QR_SIZE`). Depois disto o dashboard lê diretamente os ficheiros locais, evitando chamadas lentas ao serviço externo.
- A app pode buscar os mapas via `GET /api/maps?arena_id=123` (ou `?match_id=456`) — o endpoint devolve todos os registos de `maps` para a arena identificada.
- Para listar jogadores por equipa usa `GET /api/match/team-roster?match_id=123&side=A` com o token do match (ou token de owner). A resposta traz o array de nomes/IDs para aquele lado.
