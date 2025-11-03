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
      const p = new URLSearchParams(window.location.search);
      const y = parseInt(p.get('year'), 10);
      const m = parseInt(p.get('month'), 10); // 1..12
      const now = new Date();
      state.year = Number.isFinite(y) ? y : now.getFullYear();
      state.month = Number.isFinite(m) ? (m - 1) : now.getMonth();
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
      state.year = year;
      state.month = month;
      render();
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

    // --- API mínima para inyectar clases más adelante ---
    function getCell(dateISO){
      return grid.querySelector(`[data-date="${dateISO}"] .day-content`);
    }

    function addChip(dateISO, text){
      const target = getCell(dateISO);
      if(!target) return false;
      const chip = document.createElement('span');
      chip.className = 'class-chip';
      chip.textContent = text;
      target.appendChild(chip);
      return true;
    }

    function clearDay(dateISO){
      const target = getCell(dateISO);
      if(!target) return false;
      target.innerHTML = '';
      return true;
    }

    function addMany(entries){
      // entries: Array<{ date: 'YYYY-MM-DD', text: string }>
      entries.forEach(e => addChip(e.date, e.text));
    }

    // Exponer un handle global opcional
    window.ReservasCalendar = {
      goTo,
      render,
      addChip,
      clearDay,
      addMany
    };

    // --- Inicio ---
    parseParams();
    fillSelects();
    wireEvents();
    render();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
