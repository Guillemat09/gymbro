// public/js/reservas/reservas.js
// Calendario mensual para Reservas (Symfony). Espera a que el DOM exista.

(function(){
  function boot(){
    const grid = document.getElementById('calendarGrid');
    const yearSel = document.getElementById('yearSelect');
    const monthSel = document.getElementById('monthSelect');
    const titleEl = document.getElementById('calendarTitle');
    const prevBtn = document.getElementById('prevMonthBtn');
    const nextBtn = document.getElementById('nextMonthBtn');
    const todayBtn = document.getElementById('todayBtn');

    if(!grid || !yearSel || !monthSel || !titleEl){
      // La vista no es el calendario; nada que inicializar.
      return;
    }

    // === Endpoints configurables ===
    // Si en Twig defines window.CALENDAR_ENDPOINTS, se usará. Si no, estos por defecto.
    const ENDPOINTS = (typeof window.CALENDAR_ENDPOINTS === 'object' && window.CALENDAR_ENDPOINTS) ? window.CALENDAR_ENDPOINTS : {
      list: '/reservas/api/clases',
      detail: (id) => `/reservas/api/clase/${id}`,
      reserve: '/reservas/api/reservar',
    };

    const monthNames = [
      'enero','febrero','marzo','abril','mayo','junio',
      'julio','agosto','septiembre','octubre','noviembre','diciembre'
    ];

    const state = {
      year: null,    // número de año (p.ej. 2025)
      month: null    // 0-11 (0 = enero)
    };

    // --- Utilidades ---
    const pad = (n) => String(n).padStart(2, '0');
    const daysInMonth = (y, m) => new Date(y, m + 1, 0).getDate();
    // Índice del primer día del mes con semana empezando en Lunes: L=0 ... D=6
    const mondayFirstDayOfWeekIndex = (y, m) => (new Date(y, m, 1).getDay() + 6) % 7;

    function parseParams(){
      const init = window.CALENDAR_INIT || {};
      if (Number.isFinite(init.year) && Number.isFinite(init.month)) {
        state.year = init.year;
        state.month = init.month - 1; // 0..11
      } else {
        const base = new Date();
        state.year = base.getFullYear();
        state.month = base.getMonth();
      }
    }

    function syncParams(){
      const p = new URLSearchParams(window.location.search);
      p.set('year', state.year);
      p.set('month', state.month + 1);
      const newUrl = `${window.location.pathname}?${p.toString()}`;
      window.history.replaceState({}, '', newUrl);
    }

    function fillSelects(){
      yearSel.innerHTML = '';
      monthSel.innerHTML = '';

      const current = new Date();
      const start = current.getFullYear() - 2;
      const end = current.getFullYear() + 6;
      for(let y=start; y<=end; y++){
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        if(y === state.year) opt.selected = true;
        yearSel.appendChild(opt);
      }

      monthNames.forEach((name, idx) => {
        const opt = document.createElement('option');
        opt.value = idx;
        opt.textContent = name.charAt(0).toUpperCase() + name.slice(1);
        if(idx === state.month) opt.selected = true;
        monthSel.appendChild(opt);
      });
    }

    function render(){
      titleEl.textContent = `${monthNames[state.month].charAt(0).toUpperCase() + monthNames[state.month].slice(1)} ${state.year}`;
      grid.innerHTML = '';

      const firstOffset = mondayFirstDayOfWeekIndex(state.year, state.month);
      const totalDays = daysInMonth(state.year, state.month);

      // Mes anterior
      const prevMonth = (state.month + 11) % 12;
      const prevYear = prevMonth === 11 ? state.year - 1 : state.year;
      const prevTotal = daysInMonth(prevYear, prevMonth);

      const cells = [];
      for(let i=0; i<firstOffset; i++){
        const dayNum = prevTotal - firstOffset + i + 1;
        cells.push(buildDayCell(prevYear, prevMonth, dayNum, true));
      }

      for(let d=1; d<=totalDays; d++){
        cells.push(buildDayCell(state.year, state.month, d, false));
      }

      // Completar a 42 celdas (6 filas)
      const trailing = 42 - cells.length;
      const nextMonth = (state.month + 1) % 12;
      const nextYear = nextMonth === 0 ? state.year + 1 : state.year;
      for(let d=1; d<=trailing; d++){
        cells.push(buildDayCell(nextYear, nextMonth, d, true));
      }

      cells.forEach(c => grid.appendChild(c));

      // Sincronizar selects
      yearSel.value = String(state.year);
      monthSel.value = String(state.month);

      syncParams();

      // Pintar rápido con lo que viene del servidor (si lo hay)
      paintFromInit();
    }

    function paintFromInit() {
      const list = (window.CALENDAR_INIT && Array.isArray(window.CALENDAR_INIT.clases))
        ? window.CALENDAR_INIT.clases : [];

      // Limpia contenido de todos los días
      grid.querySelectorAll('.day .day-content').forEach(el => el.innerHTML = '');

      // Agrupa por fecha
      const byDate = list.reduce((acc, e) => {
        if (!e.date) return acc;
        (acc[e.date] ||= []).push(e);
        return acc;
      }, {});

      Object.entries(byDate).forEach(([iso, clases]) => {
        const target = grid.querySelector(`[data-date="${iso}"] .day-content`);
        if (!target) return;
        clases.sort((a, b) => String(a.time || '').localeCompare(String(b.time || '')));
        clases.forEach(entry => {
          target.appendChild(renderChip(entry));
        });
      });
    }

    function buildDayCell(year, month, day, muted){
      const cell = document.createElement('div');
      cell.className = 'day' + (muted ? ' muted' : '');
      cell.setAttribute('role', 'gridcell');

      const date = new Date(year, month, day);
      const y = date.getFullYear();
      const m = pad(date.getMonth() + 1);
      const d = pad(date.getDate());
      const iso = `${y}-${m}-${d}`;
      cell.dataset.date = iso;

      const dn = document.createElement('div');
      dn.className = 'day-number';
      dn.textContent = String(day);

      const content = document.createElement('div');
      content.className = 'day-content';

      const today = new Date();
      if(!muted && y === today.getFullYear() && (date.getMonth() === today.getMonth()) && day === today.getDate()){
        cell.classList.add('today');
        dn.setAttribute('aria-label', 'Hoy');
      }

      cell.appendChild(dn);
      cell.appendChild(content);
      return cell;
    }

    function goTo(year, month){
      while(month < 0){ month += 12; year -= 1; }
      while(month > 11){ month -= 12; year += 1; }
      // navega al servidor para traer las clases del nuevo mes:
      const params = new URLSearchParams({ year: String(year), month: String(month + 1) });
      window.location.search = params.toString();
    }

    function wireEvents(){
      prevBtn && prevBtn.addEventListener('click', () => goTo(state.year, state.month - 1));
      nextBtn && nextBtn.addEventListener('click', () => goTo(state.year, state.month + 1));
      todayBtn && todayBtn.addEventListener('click', () => {
        const t = new Date();
        goTo(t.getFullYear(), t.getMonth());
      });
      yearSel.addEventListener('change', (e) => {
        goTo(parseInt(e.target.value, 10), state.month);
      });
      monthSel.addEventListener('change', (e) => {
        goTo(state.year, parseInt(e.target.value, 10));
      });

      // Navegación con teclado (←/→): cambiar de mes
      document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') goTo(state.year, state.month - 1);
        if (e.key === 'ArrowRight') goTo(state.year, state.month + 1);
      });
    }

    // --- API mínima para inyectar clases y carga desde backend ---
    function getCell(dateISO){
      return grid.querySelector(`[data-date="${dateISO}"] .day-content`);
    }

    function renderChip(entry){
      // entry: { id, date: 'YYYY-MM-DD', name, time }
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'class-chip btn btn-sm btn-light';
      chip.textContent = entry.time ? `${entry.time} · ${entry.name}` : entry.name || 'Clase';
      chip.dataset.classId = entry.id;
      chip.addEventListener('click', () => openClassModal(entry.id));
      return chip;
    }

    async function loadMonthData(year, month){
      // Backend Symfony: GET ...?year=YYYY&month=MM (1..12)
      const q = new URLSearchParams({ year: String(year), month: String(month + 1) });
      const base = (typeof ENDPOINTS.list === 'function') ? ENDPOINTS.list(year, month + 1) : ENDPOINTS.list;
      const url  = base.includes('?') ? `${base}&${q.toString()}` : `${base}?${q.toString()}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if(!res.ok) throw new Error('No se pudo cargar el calendario');
      const data = await res.json();
      return Array.isArray(data) ? data : [];
    }

    function paintMonthEntries(entries){
      // Limpia todos los días primero
      grid.querySelectorAll('.day').forEach(day => {
        const content = day.querySelector('.day-content');
        if(content) content.innerHTML = '';
      });
      // Agrupar por fecha
      const byDate = entries.reduce((acc, e) => {
        if(!e.date) return acc;
        (acc[e.date] ||= []).push(e); return acc;
      }, {});
      Object.entries(byDate).forEach(([dateISO, list]) => {
        const target = getCell(dateISO);
        if(!target) return;
        list.sort((a,b) => String(a.time||'').localeCompare(String(b.time||'')));
        list.forEach(e => target.appendChild(renderChip(e)));
      });
    }

    async function refreshData(){
      try{
        const entries = await loadMonthData(state.year, state.month);
        paintMonthEntries(entries);
      }catch(err){
        console.error(err);
      }
    }

    // --- Modal de detalle + reserva ---
    let modal, bs, currentClassId = null;
    function ensureModal(){
      if(modal) return modal;
      const el = document.getElementById('classDetailModal');
      if(!el) return null;
      // requiere Bootstrap JS global
      bs = window.bootstrap || window.Bootstrap || null;
      if(!bs || !bs.Modal){
        console.warn('Bootstrap Modal no disponible.');
        return null;
      }
      modal = new bs.Modal(el);
      return modal;
    }

    function setModalState({loading=false, error=null, data=null}){
      const l = document.getElementById('classModalLoading');
      const e = document.getElementById('classModalError');
      const c = document.getElementById('classModalContent');
      const info = document.getElementById('classModalInfo');
      const reserveBtn = document.getElementById('cm-reserveBtn');

      l && l.classList.toggle('d-none', !loading);
      e && e.classList.toggle('d-none', !error);
      c && c.classList.toggle('d-none', !data);
      info && info.classList.add('d-none');
      if(e && error) e.textContent = error;

      if(c && data){
        (document.getElementById('cm-name') || {}).textContent      = data.name || '';
        (document.getElementById('cm-teacher') || {}).textContent   = data.teacher || '';
        (document.getElementById('cm-date') || {}).textContent      = data.date || '';
        (document.getElementById('cm-time') || {}).textContent      = data.time || '';
        (document.getElementById('cm-capacity') || {}).textContent  = data.capacity ?? '';
        (document.getElementById('cm-enrolled') || {}).textContent  = data.enrolled ?? '';
        (document.getElementById('cm-status') || {}).textContent    = data.is_full ? 'Completa' : 'Disponible';
        (document.getElementById('cm-description') || {}).textContent = data.place
          ? `Lugar: ${data.place} · Duración: ${data.duration || '-'} min`
          : (data.duration ? `Duración: ${data.duration} min` : '');
      }

      // Lógica del botón de reserva
      if (reserveBtn) {
        reserveBtn.classList.add('d-none');
        reserveBtn.disabled = false;

        if (data) {
          if (data.is_full) {
            if (info) { info.textContent = 'La clase está completa.'; info.classList.remove('d-none'); }
          } else if (data.already_reserved) {
            if (info) { info.textContent = 'Ya estás apuntado a esta clase.'; info.classList.remove('d-none'); }
          } else if (data.can_reserve) {
            reserveBtn.classList.remove('d-none');
          }
        }
      }
    }

    async function openClassModal(classId){
      const m = ensureModal();
      if(!m) return;
      currentClassId = classId;
      setModalState({loading:true});
      m.show();
      try{
        const url = (typeof ENDPOINTS.detail === 'function') ? ENDPOINTS.detail(classId) : `${ENDPOINTS.detail}/${classId}`;
        const res = await fetch(url, { headers: { 'Accept':'application/json' } });
        if(!res.ok) throw new Error(`No se pudo cargar el detalle (HTTP ${res.status})`);
        const data = await res.json();

        // Enganchar el botón de reservar
        const reserveBtn = document.getElementById('cm-reserveBtn');
        if (reserveBtn) {
          reserveBtn.onclick = async () => {
            reserveBtn.disabled = true;
            try {
              const rr = await fetch(ENDPOINTS.reserve, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
                body: JSON.stringify({ clase_id: classId })
              });
              const rj = await rr.json();
              if (!rr.ok || !rj.ok) {
                throw new Error(rj.error || 'No se pudo crear la reserva');
              }
              // actualizar contadores y estado
              data.enrolled = rj.enrolled;
              data.is_full  = rj.is_full;
              data.already_reserved = true;
              data.can_reserve = false;

              setModalState({data});
              // refrescar chips por si cambia el estado
              refreshData();
            } catch(err) {
              setModalState({data, error: err.message});
            } finally {
              reserveBtn.disabled = false;
            }
          };
        }

        setModalState({data});
      }catch(err){
        setModalState({error: err.message});
      }
    }

    // --- Inicio ---
    parseParams();
    fillSelects();
    wireEvents();
    render();       // pinta rejilla (y CALENDAR_INIT si existe)
    refreshData();  // trae datos por API para el mes actual
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
