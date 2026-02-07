// Calendar Page JavaScript
// Uses functions from kanban.js for task detail modal

let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();
let calendarTasks = [];

// Initialize from URL params
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('month')) currentMonth = parseInt(urlParams.get('month'));
    if (urlParams.has('year')) currentYear = parseInt(urlParams.get('year'));
    
    loadCalendar();
});

async function loadCalendar() {
    await loadCalendarTasks();
    renderCalendar();
    renderUpcomingTasks();
}

async function loadCalendarTasks() {
    try {
        const response = await fetch(`api/tasks.php?action=calendar&month=${currentMonth}&year=${currentYear}`);
        const data = await response.json();
        if (data.success) {
            calendarTasks = data.tasks;
        }
    } catch (error) {
        console.error('Error loading calendar tasks:', error);
    }
}

function renderCalendar() {
    const firstDay = new Date(currentYear, currentMonth - 1, 1);
    const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
    const startDay = firstDay.getDay();
    const monthName = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    // Update header
    document.getElementById('calendarMonthName').textContent = monthName;
    
    // Update navigation links
    let prevMonth = currentMonth - 1;
    let prevYear = currentYear;
    if (prevMonth < 1) { prevMonth = 12; prevYear--; }
    
    let nextMonth = currentMonth + 1;
    let nextYear = currentYear;
    if (nextMonth > 12) { nextMonth = 1; nextYear++; }
    
    document.getElementById('prevMonthBtn').href = `?month=${prevMonth}&year=${prevYear}`;
    document.getElementById('nextMonthBtn').href = `?month=${nextMonth}&year=${nextYear}`;
    document.getElementById('todayBtn').href = `?month=${new Date().getMonth() + 1}&year=${new Date().getFullYear()}`;
    
    // Organize tasks by date
    const tasksByDate = {};
    calendarTasks.forEach(task => {
        const day = new Date(task.due_date).getDate();
        if (!tasksByDate[day]) tasksByDate[day] = [];
        tasksByDate[day].push(task);
    });
    
    // Build calendar grid
    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';
    
    const today = new Date().toISOString().split('T')[0];
    
    // Empty cells before first day
    for (let i = 0; i < startDay; i++) {
        calendarDays.innerHTML += '<div class="calendar-day empty"></div>';
    }
    
    // Days of month
    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = currentDate === today;
        const hasTasks = tasksByDate[day] && tasksByDate[day].length > 0;
        
        let dayHtml = `<div class="calendar-day ${isToday ? 'today' : ''} ${hasTasks ? 'has-tasks' : ''}">`;
        dayHtml += `<span class="day-number">${day}</span>`;
        
        if (hasTasks) {
            dayHtml += '<div class="day-tasks">';
            const tasksToShow = tasksByDate[day].slice(0, 3);
            tasksToShow.forEach(task => {
                const priorityColors = {
                    high: '#e74c3c',
                    medium: '#f39c12',
                    low: '#27ae60'
                };
                const borderColor = priorityColors[task.priority] || '#3498db';
                const title = task.title.length > 20 ? task.title.substring(0, 20) + '...' : task.title;
                dayHtml += `
                    <div class="day-task priority-${task.priority}" 
                         style="border-left-color: ${borderColor}"
                         onclick="openTaskDetail(${task.id})">
                        <span class="day-task-title">${escapeHtml(title)}</span>
                    </div>`;
            });
            
            if (tasksByDate[day].length > 3) {
                dayHtml += `<div class="day-task-more">+${tasksByDate[day].length - 3} more</div>`;
            }
            dayHtml += '</div>';
        }
        
        dayHtml += '</div>';
        calendarDays.innerHTML += dayHtml;
    }
    
    // Empty cells after last day
    const totalCells = startDay + daysInMonth;
    const remainingCells = (7 - (totalCells % 7)) % 7;
    for (let i = 0; i < remainingCells; i++) {
        calendarDays.innerHTML += '<div class="calendar-day empty"></div>';
    }
}

function renderUpcomingTasks() {
    const container = document.getElementById('upcomingTasks');
    const today = new Date().toISOString().split('T')[0];
    
    // Filter and sort upcoming tasks
    const upcomingTasks = calendarTasks
        .filter(task => task.due_date >= today && task.status !== 'done')
        .sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
        .slice(0, 5);
    
    if (upcomingTasks.length === 0) {
        container.innerHTML = '<p class="no-tasks">No upcoming deadlines this month</p>';
        return;
    }
    
    container.innerHTML = upcomingTasks.map(task => {
        const dueDate = new Date(task.due_date);
        const day = dueDate.getDate().toString().padStart(2, '0');
        const month = dueDate.toLocaleDateString('en-US', { month: 'short' });
        
        return `
            <div class="upcoming-task" onclick="openTaskDetail(${task.id})">
                <div class="upcoming-date">
                    <span class="date-day">${day}</span>
                    <span class="date-month">${month}</span>
                </div>
                <div class="upcoming-info">
                    <h4>${escapeHtml(task.title)}</h4>
                    <span class="project-name" style="color: ${task.project_color || '#3498db'}">
                        ${escapeHtml(task.project_name || 'No Project')}
                    </span>
                </div>
                <div class="upcoming-assignee">
                    ${escapeHtml(task.assigned_name || 'Unassigned')}
                </div>
            </div>`;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function navigateMonth(direction) {
    currentMonth += direction;
    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
    
    // Update URL without reload
    const newUrl = `?month=${currentMonth}&year=${currentYear}`;
    window.history.pushState({}, '', newUrl);
    
    loadCalendar();
}
