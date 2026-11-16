# Decisões de arquitetura

Registro curto das decisões técnicas (o quê, por quê, alternativa descartada).

## ADR-001 — Projeto novo com transplante de módulos da GFarma

- **Decisão:** Laravel 12 limpo; módulos prontos da farmácia (checkout/Asaas, carrinho, cupons, endereços, WhatsApp, recuperação de carrinho, deploy) são copiados um a um e adaptados.
- **Por quê:** o schema da farmácia é acoplado ao ERP Trier (`codigo_trier` obrigatório), tem regras regulatórias (tarja, receita, ANVISA) e não suporta variações de produto. Um fork herdaria ~100 migrations, essas regras e os achados pendentes do `AUDITORIA.md`.
- **Descartado:** fork completo do repositório da farmácia.

## ADR-002 — Produto com variações (SKU por variante)

- **Decisão:** `products` (dados de vitrine) + `product_variants` (SKU, volume/cor, preço, estoque). Carrinho e pedido referenciam a variante.
- **Por quê:** perfumaria vende o mesmo item em 30/50/100 ml e maquiagem em tons; estoque e preço são por variante.

## ADR-003 — Sem ERP no MVP

- **Decisão:** catálogo e estoque cadastrados no painel admin. Integração com sistema da loja física fica para depois, se existir um com API.

## ADR-004 — Mesma stack/infra da GFarma

- **Decisão:** Blade + Alpine + Tailwind 3, MySQL 8, Redis, Swarm + Traefik. Mantém as views/componentes da farmácia reaproveitáveis e a operação idêntica.
- **Diferenças intencionais no deploy:** nenhum segredo hardcoded no `docker-compose.prod.yml` (todas via variáveis do stack), nginx em imagem própria (`Dockerfile.web`), migrations só no serviço `app`, `LOG_LEVEL=warning` em produção, middlewares/routers do Traefik com prefixo `lara-` para não colidir com a stack da farmácia no mesmo Swarm.
