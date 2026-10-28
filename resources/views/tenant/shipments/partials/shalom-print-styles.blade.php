<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #e5e7eb; color: #0f172a; }
    .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center;
           padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; }
    .bar a, .bar button { border: 1px solid #e2e8f0; background: #fff; color: #334155; border-radius: 9px; padding: 8px 14px;
           font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
    .bar a.on { background: #0f172a; border-color: #0f172a; color: #fff; }
    .bar .go { background: #16a34a; border-color: #16a34a; color: #fff; }
    .bar .go[disabled] { opacity: .55; cursor: wait; }
    .bar .fmt { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; font-weight: 600; }
    .bar select { border: 1px solid #cbd5e1; border-radius: 9px; padding: 7px 10px; font-size: 14px; background: #fff; color: #0f172a; }
    .bar small { color: #64748b; font-size: 12.5px; width: 100%; text-align: center; }
    .estado { max-width: 720px; margin: 16px auto; padding: 12px 16px; border-radius: 10px; background: #fff; color: #334155; font-size: 14px; text-align: center; }
    .estado.warn { background: #fff7e6; color: #8a5300; }
    iframe { display: block; width: 100%; height: calc(100vh - 92px); border: 0; background: #fff; }
</style>
