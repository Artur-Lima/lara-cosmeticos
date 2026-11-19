#!/usr/bin/env bash
# Gera e publica as imagens da aplicação e do nginx com a mesma tag.
# Uso: ./docker-publish.sh <usuario-dockerhub> [tag]
#   tag padrão = commit atual (ex.: 6e5a655). Também publica :latest.
set -euo pipefail

USER_HUB="${1:?Informe o usuário do Docker Hub: ./docker-publish.sh usuario [tag]}"
TAG="${2:-$(git rev-parse --short HEAD)}"
APP="${USER_HUB}/lara-cosmeticos"
WEB="${USER_HUB}/lara-cosmeticos-web"

if [ -n "$(git status --porcelain)" ]; then
    echo "Há alterações não commitadas. Commite antes de publicar para a tag bater com o código." >&2
    exit 1
fi

docker buildx build --platform linux/amd64 -t "${APP}:${TAG}" -t "${APP}:latest" --push .
docker buildx build --platform linux/amd64 -f docker/Dockerfile.web \
    --build-arg APP_IMAGE="${APP}:${TAG}" \
    -t "${WEB}:${TAG}" -t "${WEB}:latest" --push .

echo
echo "Publicado:"
echo "  APP_IMAGE=${APP}:${TAG}"
echo "  WEB_IMAGE=${WEB}:${TAG}"
echo "Atualize essas duas variáveis no stack do Portainer e clique em Update."
