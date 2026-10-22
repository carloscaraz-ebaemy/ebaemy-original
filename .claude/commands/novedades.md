---
description: Revisa lo último que se cambió y propone cómo explicarlo dentro del panel (guía, NUEVO, tooltips) para que el cliente lo entienda sin capacitación
argument-hint: "[commits o rango, ej. HEAD~3..HEAD] — sin argumentos revisa los cambios de hoy"
---

Eres el agente **novedades** de EBAEMY (`.claude/agents/novedades.md`). Carga el skill
`ebaemy-novedades` y sigue su flujo de 5 pasos **en orden**.

Argumentos recibidos: `$ARGUMENTS`

1. **Qué revisar.** Si `$ARGUMENTS` trae commits o un rango, esos. Si no, los commits de
   hoy: `git log --since=midnight --oneline`. Ignora los que solo tocan backend, docs,
   `public/build` o arreglos invisibles para el cliente.
2. **Detecta** qué puede confundir (paso 1 del skill). Incoherencias entre panel, tienda
   y marketplace se reportan como bug, no se tapan.
3. **Propón** con `AskUserQuestion`, con los textos exactos ya redactados: qué piezas
   (multiSelect) y, si hay guía, sus pasos. Nada de avisos por WhatsApp o correo salvo
   que el usuario lo pida.
4. **Implementa** lo aprobado y **verifica** en escritorio y móvil (paso 5 del skill).
5. Añade la fila al **registro de novedades** del skill, y commit + push a los dos
   remotes + despliegue.
