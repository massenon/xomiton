<script setup lang="ts">
// Carte schématique du Bénin de l'annuaire (lot annuaire-membre, P8-12 ; prototype annuaire.php l.73-78) : contour SVG
// décoratif (directory/map.ts, sans Leaflet : Q15 b) et une pastille par département pourvu, lien `?zone=` (adresse
// gardée, clic intercepté par la page : visite partielle) au nombre de fiches des filtres en cours ; pastille du
// département choisi en rouge (prototype : bg-danger). Fiches nationales annoncées sous la carte. Jetons --xm-* seulement.
// Props : zones (BrowseDirectory::map), national, selected, href (adresse d'un département). Émet `select`.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { OUTLINE, VIEW, mapPins } from '@/directory/map';
import type { MapZone } from '@/directory/types';

const props = withDefaults(defineProps<{
    zones: MapZone[];
    national?: number;
    selected?: number | null;
    href: (zoneId: number) => string;
}>(), { national: 0, selected: null });

const emit = defineEmits<{ select: [zoneId: number] }>();

const { t } = useI18n();
const pins = computed(() => mapPins(props.zones, props.selected));

function onClick(event: MouseEvent, zoneId: number) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    emit('select', zoneId);
}
</script>

<template>
    <figure class="xm-dir-map mb-0" :aria-labelledby="'xm-dir-map-caption'">
        <div class="xm-dir-map__canvas" :style="{ aspectRatio: `${VIEW.width} / ${VIEW.height}` }">
            <svg class="xm-dir-map__shape" :viewBox="`0 0 ${VIEW.width} ${VIEW.height}`" aria-hidden="true" focusable="false">
                <path :d="OUTLINE" />
            </svg>
            <ul class="list-unstyled mb-0">
                <li v-for="pin in pins" :key="pin.id" class="xm-dir-map__pin" :style="{ left: pin.left, top: pin.top }">
                    <a
                        :class="['badge rounded-pill text-decoration-none', pin.selected ? 'bg-danger' : 'bg-primary']"
                        :href="href(pin.id)"
                        :title="t('directory.map.pin', { zone: pin.name, count: pin.count }, pin.count)"
                        :aria-label="t('directory.map.pin', { zone: pin.name, count: pin.count }, pin.count)"
                        :aria-current="pin.selected ? 'true' : undefined"
                        @click="onClick($event, pin.id)"
                    >{{ pin.count }}</a>
                </li>
            </ul>
        </div>
        <figcaption id="xm-dir-map-caption" class="small text-secondary text-center mt-1">
            {{ t('directory.map.caption') }}<template v-if="national > 0"> · {{ t('directory.map.national', { count: national }, national) }}</template>
        </figcaption>
    </figure>
</template>
