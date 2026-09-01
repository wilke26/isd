#!/bin/sh

set -eu

legacy_volume="${ATTACHMENTS_LEGACY_VOLUME:-it-service-desk_ticket-attachments-data}"
project_root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
destination="${ATTACHMENTS_DESTINATION:-${project_root}/storage/app/private}"

if ! docker volume inspect "$legacy_volume" >/dev/null 2>&1; then
    echo "Kein Legacy-Volume ${legacy_volume} gefunden; es ist keine Migration nötig."
    exit 0
fi

mkdir -p "$destination"

echo "Führe ${legacy_volume} mit ${destination} zusammen ..."

# The parent release temporarily mounted this volume over storage/app/private.
# Copy only paths which are not already present: older files in the project
# bind mount remain authoritative and random attachment paths do not collide in
# normal operation. The volume is deliberately retained as a rollback copy.
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --mount "type=volume,src=${legacy_volume},dst=/source,readonly" \
    --mount "type=bind,src=${destination},dst=/destination" \
    busybox:1.37.0 \
    sh -ec '
        before=$(find /destination -type f | wc -l | tr -d " ")
        find /source -type f -exec sh -ec '\''
            source_path=$1
            relative_path=${source_path#/source/}
            target_path=/destination/${relative_path}

            if [ ! -e "$target_path" ]; then
                mkdir -p "$(dirname "$target_path")"
                cp "$source_path" "$target_path"
            fi
        '\'' sh {} \;
        after=$(find /destination -type f | wc -l | tr -d " ")
        echo "Dateien vorher: ${before}; nachher: ${after}"
    '

echo "Migration abgeschlossen. Das Legacy-Volume wurde nicht gelöscht."
