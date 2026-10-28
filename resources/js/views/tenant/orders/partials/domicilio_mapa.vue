<template>
    <div class="dm">
        <label class="dm-lbl req">Dirección de entrega</label>
        <input
            ref="dir"
            :value="value.shipping_destination"
            type="text"
            class="dm-input"
            maxlength="500"
            autocomplete="off"
            placeholder="Ej. Av. Arequipa 1234, Miraflores"
            @input="set({ shipping_destination: $event.target.value })"
            @keydown.enter.prevent
        />
        <small class="dm-hint">Escribe y elige la dirección de la lista; luego ajusta el marcador si hace falta.</small>

        <template v-if="config && config.maps_key">
            <div class="dm-paste">
                <input v-model="pegado" type="text" class="dm-input dm-input--sm"
                       placeholder="O pega la ubicación que mandó por WhatsApp (enlace de Google Maps o coordenadas)"
                       @keydown.enter.prevent="usarPegado" />
                <button type="button" class="dm-btn" :disabled="!pegado" @click="usarPegado">Usar</button>
            </div>
            <div ref="mapa" class="dm-map"></div>
            <div v-if="value.latitude" class="dm-picked">
                📍 Ubicación fijada{{ value.destination_city ? " · " + value.destination_city : "" }}
                <template v-if="value.distance_text"> · {{ value.distance_text }} desde la tienda<template v-if="value.duration_text"> (~{{ value.duration_text }})</template></template>
            </div>
            <p v-if="!config.store" class="dm-warn">La tienda no tiene su ubicación configurada: no se puede calcular la distancia ni el costo.</p>
        </template>
        <p v-else class="dm-warn">El mapa no está disponible: escribe la dirección completa y una referencia clara.</p>

        <div class="dm-row">
            <div class="dm-f">
                <label class="dm-lbl">Costo del envío (S/)</label>
                <input :value="value.delivery_price" type="number" min="0" step="0.10" inputmode="decimal" class="dm-input"
                       placeholder="—" @input="set({ delivery_price: $event.target.value })" />
                <small class="dm-hint">{{ cotizado !== null ? "Cotizado según la distancia: S/ " + cotizado.toFixed(2) + (aprox ? " (línea recta, aprox.)" : "") + ". Puedes ajustarlo." : "Se calcula al fijar la ubicación." }}</small>
            </div>
            <div class="dm-f dm-f--2">
                <label class="dm-lbl">Referencia</label>
                <input :value="value.reference" type="text" maxlength="255" class="dm-input"
                       placeholder="Dpto 302, portón negro, frente al parque…" @input="set({ reference: $event.target.value })" />
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Reparto a domicilio con motorizado, igual que en el enlace del cliente
 * (tenant/shipments/public.blade.php): Places + pin arrastrable + geocoding
 * inverso, distancia de manejo con Distance Matrix y, si Google no da la
 * ruta, línea recta (la misma fórmula que ShippingRequest::haversineKm).
 * Precio = base + km × tarifa, nunca menor al mínimo.
 */
const LIMA = { lat: -12.0464, lng: -77.0428 };
let cargaMaps = null;

function cargarMaps(key) {
    if (window.google && window.google.maps && window.google.maps.places) return Promise.resolve();
    if (cargaMaps) return cargaMaps;
    cargaMaps = new Promise((ok, mal) => {
        window.__ebDomMapsReady = () => ok();
        const s = document.createElement("script");
        s.src = "https://maps.googleapis.com/maps/api/js?key=" + encodeURIComponent(key) + "&libraries=places&callback=__ebDomMapsReady&language=es&region=PE";
        s.async = true;
        s.onerror = () => { cargaMaps = null; mal(new Error("maps")); };
        document.head.appendChild(s);
    });
    return cargaMaps;
}

export default {
    props: {
        value: { type: Object, required: true },
        config: { type: Object, default: null },
    },
    data() {
        return { pegado: "", cotizado: null, aprox: false, seq: 0 };
    },
    mounted() {
        if (!this.config || !this.config.maps_key) return;
        cargarMaps(this.config.maps_key).then(this.iniciar).catch(() => {});
    },
    methods: {
        set(cambios) {
            this.$emit("input", Object.assign({}, this.value, cambios));
        },
        iniciar() {
            const g = window.google.maps;
            const tiene = this.value.latitude && this.value.longitude;
            const inicio = tiene ? { lat: Number(this.value.latitude), lng: Number(this.value.longitude) } : LIMA;
            this.geocoder = new g.Geocoder();
            this.mapa = new g.Map(this.$refs.mapa, { center: inicio, zoom: tiene ? 16 : 12, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
            this.pin = new g.Marker({ map: this.mapa, position: inicio, draggable: true });
            this.pin.addListener("dragend", () => this.inverso(this.pin.getPosition()));
            this.mapa.addListener("click", e => { this.poner(e.latLng); this.inverso(e.latLng); });
            const ac = new g.places.Autocomplete(this.$refs.dir, { componentRestrictions: { country: "pe" }, fields: ["geometry", "formatted_address", "address_components", "place_id"] });
            ac.bindTo("bounds", this.mapa);
            ac.addListener("place_changed", () => {
                const p = ac.getPlace();
                if (!p.geometry) return;
                this.poner(p.geometry.location);
                this.aplicar(p.geometry.location.lat(), p.geometry.location.lng(), p.formatted_address, p.address_components, p.place_id, true);
            });
        },
        poner(latlng) {
            this.pin.setPosition(latlng);
            this.mapa.panTo(latlng);
            if (this.mapa.getZoom() < 16) this.mapa.setZoom(16);
        },
        inverso(latlng) {
            this.geocoder.geocode({ location: latlng }, (r, st) => {
                const ok = st === "OK" && r[0];
                this.aplicar(latlng.lat(), latlng.lng(), ok ? r[0].formatted_address : "", ok ? r[0].address_components : null, ok ? r[0].place_id : "", false);
            });
        },
        ciudad(comp) {
            if (!comp) return "";
            const t = ty => (comp.find(c => c.types.indexOf(ty) !== -1) || {}).long_name || "";
            return t("locality") || t("sublocality") || t("administrative_area_level_2") || t("administrative_area_level_1");
        },
        aplicar(lat, lng, formatted, comp, placeId, delBuscador) {
            const escrito = (this.value.shipping_destination || "").trim();
            const cambios = {
                latitude: lat, longitude: lng, google_place_id: placeId || "", formatted_address: formatted || "",
                google_maps_url: "https://www.google.com/maps/search/?api=1&query=" + lat + "," + lng,
                destination_city: this.ciudad(comp) || this.value.destination_city || "",
                distance_km: "", distance_text: "", duration_text: "",
            };
            // Mover el pin no pisa lo que el operador escribió a mano (número, dpto).
            if (formatted && (delBuscador || !escrito || escrito === (this.value.formatted_address || "").trim())) {
                cambios.shipping_destination = formatted;
            }
            this.set(cambios);
            this.distancia(lat, lng);
        },
        usarPegado() {
            const t = (this.pegado || "").replace(/%2C/gi, ",");
            const m = t.match(/@(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)/) || t.match(/(-?\d{1,2}\.\d{3,})\s*,\s*(-?\d{1,3}\.\d{3,})/);
            if (!m || !window.google) {
                this.$message.warning("No encontramos coordenadas ahí. Pega el enlace largo de Google Maps o «-12.12, -77.03». Los enlaces cortos (maps.app.goo.gl) ábrelos primero y copia el largo.");
                return;
            }
            const ll = new window.google.maps.LatLng(Number(m[1]), Number(m[2]));
            this.poner(ll);
            this.inverso(ll);
            this.pegado = "";
        },
        cotizar(km) {
            const p = this.config && this.config.pricing;
            if (!p) return null;
            const v = Math.max(p.base + km * p.per_km, p.min);
            return Math.round(v * 100) / 100;
        },
        recta(a, b) {
            const r = Math.PI / 180;
            const h = Math.sin(((b.lat - a.lat) * r) / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(((b.lng - a.lng) * r) / 2) ** 2;
            return Math.round(6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)) * 100) / 100;
        },
        distancia(lat, lng) {
            this.cotizado = null;
            const tienda = this.config && this.config.store;
            if (!tienda) return;
            const n = ++this.seq;
            const listo = (km, texto, dur, aprox) => {
                if (n !== this.seq) return;
                this.aprox = aprox;
                this.cotizado = this.cotizar(km);
                this.set({ distance_km: km.toFixed(2), distance_text: texto, duration_text: dur || "", delivery_price: this.cotizado !== null ? this.cotizado.toFixed(2) : this.value.delivery_price });
            };
            const respaldo = () => { const km = this.recta(tienda, { lat, lng }); listo(km, km + " km aprox.", "", true); };
            try {
                new window.google.maps.DistanceMatrixService().getDistanceMatrix(
                    { origins: [tienda], destinations: [{ lat, lng }], travelMode: "DRIVING", unitSystem: window.google.maps.UnitSystem.METRIC },
                    (res, st) => {
                        const el = st === "OK" && res.rows[0] && res.rows[0].elements[0];
                        if (!el || el.status !== "OK") return respaldo();
                        listo(el.distance.value / 1000, el.distance.text, el.duration.text, false);
                    }
                );
            } catch (e) { respaldo(); }
        },
    },
};
</script>

<style scoped>
.dm-lbl { display: block; font-size: 13px; font-weight: 700; color: #0f172a; margin: 10px 0 6px; }
.dm-lbl.req::after { content: " *"; color: #dc2626; }
.dm-input { width: 100%; height: 42px; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 0 12px; font-size: 14px; background: #fff; }
.dm-input:focus { outline: none; border-color: #6366f1; }
.dm-input--sm { height: 38px; font-size: 13px; }
.dm-hint { display: block; font-size: 12px; color: #64748b; margin-top: 4px; }
.dm-paste { display: flex; gap: 8px; margin-top: 10px; }
.dm-btn { border: 1.5px solid #c7d2fe; background: #eef2ff; color: #3730a3; border-radius: 10px; padding: 0 14px; font-weight: 700; cursor: pointer; }
.dm-btn[disabled] { opacity: .5; cursor: default; }
.dm-map { width: 100%; height: 260px; border-radius: 12px; border: 1.5px solid #e2e8f0; margin-top: 10px; background: #eef2f7; }
.dm-picked { margin-top: 8px; padding: 8px 12px; background: #ecfdf5; color: #065f46; border-radius: 10px; font-size: 13px; font-weight: 600; }
.dm-warn { margin: 8px 0 0; padding: 8px 12px; background: #fff7e6; color: #8a5300; border-radius: 10px; font-size: 13px; }
.dm-row { display: grid; grid-template-columns: 1fr 2fr; gap: 12px; }
@media (max-width: 640px) { .dm-row { grid-template-columns: 1fr; } }
</style>
