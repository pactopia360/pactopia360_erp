// state.js

// ===== Cache de último payload =====
export let cache = null;
export function setCache(d){ cache = d; }
export function clearCache(){ cache = null; }

// ===== Tema actual (para re-pintar charts si cambia) =====
export let currentThemeLight = document.body.classList.contains('theme-light');
export function setCurrentThemeLight(v){ currentThemeLight = !!v; }

// ===== Deduplicación de consultas =====
export let lastQueryKey = '';
export function setLastQueryKey(v){ lastQueryKey = v || ''; }

// ===== Control de fetch en curso (AbortController) =====
export let loadingCtrl = null;
export function setLoadingCtrl(ctrl){ loadingCtrl = ctrl || null; }
export function abortCurrent(){
  try { loadingCtrl?.abort?.(); } catch {}
  // Limpia referencia para evitar reusos accidentales
  loadingCtrl = null;
}
export function isLoading(){ return !!loadingCtrl; }

// ===== Referencias a instancias de Chart.js (para resize/destroy) =====
export const chartsRef = {
  income: null,
  stamps: null,
  plans: null,
  // nuevos módulos:
  incomePlan: null,
  newClients: null,
  topClients: null,
  scatter: null,
  mom: null, // variación mensual (%)
};

// Utilidad opcional para limpiar todas las refs (no obligatoria)
export function clearChartsRef(){
  for (const k of Object.keys(chartsRef)) chartsRef[k] = null;
}
