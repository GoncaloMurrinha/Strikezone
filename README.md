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
```forma mais rapida de inicializar 
git clone https://github.com/Goncalo-Murrinha/Strikezone.git
cd Strikezone/central-app
composer install
& "C:\xampp\mysql\bin\mysql.exe" -u root -p airsoft_central < .\sql\schema.sql
Start-Service Memurai
php -S 0.0.0.0:8080 -t public

http://localhost:8080
