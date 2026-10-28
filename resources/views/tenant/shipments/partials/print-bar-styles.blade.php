{{-- Barra de impresión de rótulos: el mismo lenguaje que la pantalla de
     Shalom (partials/shalom-print-ui). La usan el rótulo suelto y el lote. --}}
<style>
    .pb { --ink: oklch(0.24 0.02 258); --ink-2: oklch(0.42 0.018 258); --muted: oklch(0.56 0.015 258);
          --line: oklch(0.9 0.006 250); --line-2: oklch(0.84 0.008 250); --panel: oklch(0.992 0.002 250); --panel-2: oklch(0.972 0.004 250);
          --accent: oklch(0.47 0.13 262); --accent-soft: oklch(0.955 0.025 262); --warn: oklch(0.52 0.11 70); --warn-soft: oklch(0.97 0.035 85);
          --ok: oklch(0.48 0.11 150); --brand-sh: oklch(0.55 0.2 25);
          font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, Roboto, sans-serif; color: var(--ink); }
    .pb-bar { position: sticky; top: 0; z-index: 999; margin-bottom: 16px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
              padding: 10px 20px; background: var(--panel); border-bottom: 1px solid var(--line); }
    .pb-t b { display: block; font-size: 15px; font-weight: 650; }
    .pb-t span { font-size: 12.5px; color: var(--muted); }
    .pb-sp { flex: 1; }
    .pb-seg { display: inline-grid; grid-auto-flow: column; background: var(--panel-2); border: 1px solid var(--line); border-radius: 9px; padding: 3px; }
    .pb-seg button { border: 0; background: none; font: 600 13px/1 inherit; color: var(--ink-2); padding: 7px 14px; border-radius: 6px; cursor: pointer; }
    .pb-seg button[aria-pressed="true"] { background: var(--panel); color: var(--ink); box-shadow: 0 1px 2px oklch(0.3 0.02 258 / 0.14); }
    .pb-chk { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: var(--ink-2); cursor: pointer; }
    .pb-chk input { width: 16px; height: 16px; accent-color: var(--accent); }
    .pb-btn { display: inline-flex; align-items: center; gap: 7px; height: 38px; padding: 0 16px; border-radius: 9px; border: 1px solid var(--line-2);
              background: var(--panel); color: var(--ink); font: 600 13.5px/1 inherit; text-decoration: none; cursor: pointer; }
    .pb-btn:hover { background: var(--panel-2); }
    .pb-btn svg { width: 16px; height: 16px; }
    .pb-btn--primary { background: var(--accent); border-color: var(--accent); color: oklch(0.99 0.003 262); }
    .pb-btn--primary:hover { background: oklch(0.42 0.13 262); }
    .pb-btn[disabled], .pb-btn[aria-disabled="true"] { opacity: .5; pointer-events: none; }
    .pb-btn:focus-visible, .pb-seg button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .pb-box { max-width: 820px; margin: 0 auto 14px; background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
    .pb-box h2 { font-size: 14.5px; font-weight: 650; margin: 0 0 2px; display: flex; align-items: center; gap: 8px; }
    .pb-box p { margin: 0 0 12px; color: var(--ink-2); font-size: 13px; }
    .pb-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand-sh); }
    .pb-acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .pb-warn { background: var(--warn-soft); border-color: oklch(0.9 0.05 85); color: var(--warn); font-size: 13px; }
    .pb-warn b { color: oklch(0.42 0.1 65); }
    .pb-res { margin-top: 10px; font-size: 13px; }
    .pb-res ul { margin: 4px 0 0; padding-left: 18px; color: var(--warn); }
    .pb-empty { max-width: 820px; margin: 0 auto 14px; text-align: center; color: var(--muted); font-size: 13px; }
    @media (max-width: 720px) { .pb-sp { display: none; } .pb-bar { gap: 10px; } }
</style>
