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

## ADR-005 — Estoque baixa na confirmação do pagamento

- **Decisão:** o estoque da variante só é baixado quando o Asaas confirma o pagamento, via `ProductVariant::decrementStock()` — um único `UPDATE ... WHERE stock >= qtd`, atômico.
- **Consequência aceita:** dois clientes podem pagar pelo último item. A baixa atômica garante que o estoque nunca fica negativo (só uma confirmação vence); o pedido perdedor deve ser sinalizado para a loja resolver (estorno ou troca). O checkout bloqueia variantes com estoque zerado para reduzir esse caso.
- **Descartado:** reserva de estoque na criação do pedido com expiração (mais segura contra overselling, porém mais complexa).

## ADR-006 — Login obrigatório para comprar

- **Decisão:** checkout exige conta (cadastro rápido + login Google no módulo de auth). O Asaas exige CPF e a conta habilita recuperação de carrinho e contato via WhatsApp.
- **Segurança:** `users.role` não é mass-assignable; promoção a admin só via `php artisan user:make-admin {email}`.

## ADR-007 — Entrega própria só em Itapuã do Oeste + retirada na loja

- **Decisão:** duas modalidades: entrega (somente Itapuã do Oeste/RO, taxa por bairro em `shipping_zones`, com frete grátis opcional acima de um valor) e retirada na loja (sem taxa). Sem Correios/transportadora no MVP.
- **Taxas reais** são cadastradas pela loja no admin; o seed de produção não cria zonas.

## ADR-008 — Pagamento pelo Asaas: PIX na loja, cartão na página do Asaas

- **PIX:** cobrança criada via API e QR Code/copia e cola exibidos na página do pedido, que consulta o status a cada 5 s.
- **Cartão:** o cliente é redirecionado para a fatura (`invoiceUrl`) do Asaas. O número do cartão nunca passa pelo servidor da loja, então a loja fica fora do escopo de PCI. Parcelamento fica para depois.
- **Webhook** (`POST /api/asaas/webhook`): valida `asaas-access-token` com `hash_equals` (fechado se o token não estiver configurado), grava o evento com `event_id` único (reentregas ignoradas) e processa na fila. Antes de marcar como pago, consulta a cobrança na API do Asaas e confere status e valor.
- **Casos de borda tratados:** CONFIRMED e RECEIVED do mesmo pagamento (baixa uma vez só, com lock), item sem estoque na hora do pagamento (pedido pago e marcado "precisa de atenção"), pagamento depois do cancelamento (alerta), estorno (cancela, devolve só o estoque que saiu e alerta), preço alterado durante o checkout (cliente revisa antes de pagar).
- **Estorno do dinheiro** é manual no painel do Asaas; o sistema devolve o estoque e avisa.
