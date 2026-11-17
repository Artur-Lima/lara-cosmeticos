# Lara Lima Cosméticos — E-commerce

Loja online de perfumaria e cosméticos da Lara Lima Cosméticos (Itapuã do Oeste – RO), com checkout automático (Asaas) e atendimento via WhatsApp. Mantido pela CyberSelva.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.4 · Laravel 12 |
| Frontend | Blade · Alpine.js · Tailwind CSS 3 (Vite) |
| Dados | MySQL 8 · Redis (cache, sessão, fila) |
| Infra | Docker · Docker Swarm + Traefik (Portainer) |
| Integrações | Asaas (PIX/cartão) · n8n/Evolution API (WhatsApp) |

Decisões de arquitetura: [`docs/decisoes.md`](docs/decisoes.md).

## Rodando localmente (Docker)

```bash
cp .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d --build
docker compose --profile front run --rm node sh -c "npm install && npm run build"
```

Acesse http://localhost:8000. Para hot reload do front: `docker compose --profile front up node`.

Popular o banco local (marcas, categorias, produtos de demonstração e admin de teste):

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Admin local: `admin@lara.test` / `password` → http://localhost:8000/admin

## Administradores

O papel de admin nunca vem de formulário. Para promover alguém (o usuário precisa se cadastrar no site antes):

```bash
php artisan user:make-admin lara@exemplo.com          # promove
php artisan user:make-admin lara@exemplo.com --revoke # volta a cliente
```

MySQL do container fica exposto em `localhost:3307` (usuário/senha do `.env`).

## Rodando sem Docker

Requisitos: PHP 8.4 (ext: pdo_mysql, mbstring, bcmath, gd, intl, zip, redis), Composer 2, Node 20, MySQL 8 e Redis.

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install && npm run build
composer run dev
```

## Testes e qualidade

```bash
php artisan test        # SQLite em memória (phpunit.xml)
vendor/bin/pint         # padronização de código
```

O CI (`.github/workflows/ci.yml`) roda Pint, build dos assets e testes a cada push/PR na `main`.
**`composer.lock` e `package-lock.json` devem ser versionados** — o build da imagem e o CI dependem deles.

## Deploy (Swarm)

```bash
# 1. Imagens (app + web)
docker buildx build --platform linux/amd64 -t <usuario>/lara-cosmeticos:<tag> --push .
docker buildx build --platform linux/amd64 -f docker/Dockerfile.web \
  --build-arg APP_IMAGE=<usuario>/lara-cosmeticos:<tag> -t <usuario>/lara-cosmeticos-web:<tag> --push .

# 2. Stack no Portainer usando docker-compose.prod.yml, com as variáveis:
#    APP_IMAGE, WEB_IMAGE, APP_KEY, APP_DOMAIN, SWARM_NODE, DB_PASSWORD, DB_ROOT_PASSWORD
#    (+ ASAAS_*, MAIL_*, WHATSAPP_* quando os módulos forem ativados)
```

Só o serviço `app` executa migrations no boot (`RUN_MIGRATIONS=true`).

## Convenções

- Commits: [Conventional Commits](https://www.conventionalcommits.org/pt-br/) (`feat:`, `fix:`, `chore:`, `refactor:`, `docs:`, `test:`).
- Branches: `main` (produção) · `feat/<modulo>` · `fix/<assunto>`.
- `refrencia/` (código da farmácia GFarma, base para transplante de módulos) e `imagens/` ficam fora do Git.
