// Tasks example: every action goes through the same capabilities that REST and MCP use
import { listTasks, createTask, updateTask, deleteTask, CapabilityError } from './capabilities.js'

const list = document.getElementById('list')
const empty = document.getElementById('empty')
const form = document.getElementById('new-task')
const errorBox = document.getElementById('error')
const tabs = document.querySelectorAll('.cq-tabs button')
let status = 'all'
let editing = false

function showError (e) {
  if (e instanceof CapabilityError) {
    errorBox.textContent = Object.keys(e.fields).length ? Object.values(e.fields).join(' ') : e.message
  } else {
    errorBox.textContent = 'Something went wrong. Try again.'
    console.error(e)
  }
}

async function run (action) {
  errorBox.textContent = ''
  try {
    await action()
    await refresh()
  } catch (e) {
    showError(e)
  }
}

function button (label, className, onClick) {
  const b = document.createElement('button')
  b.type = 'button'
  b.className = 'btn ' + className
  b.textContent = label
  b.addEventListener('click', onClick)
  return b
}

function row (task) {
  const done = Boolean(Number(task.done))
  const li = document.createElement('li')
  li.className = 'cq-item' + (done ? ' is-done' : '')

  const check = document.createElement('input')
  check.type = 'checkbox'
  check.className = 'form-check-input cq-check'
  check.checked = done
  check.setAttribute('aria-label', (done ? 'Reopen ' : 'Complete ') + task.title)
  check.addEventListener('change', () => run(() => updateTask({ id: task.id, done: check.checked })))

  const body = document.createElement('div')
  body.className = 'cq-item-body'
  const title = document.createElement('p')
  title.className = 'cq-item-title'
  title.textContent = task.title
  body.append(title)
  if (task.notes) {
    const notes = document.createElement('p')
    notes.className = 'cq-item-meta'
    notes.textContent = task.notes
    body.append(notes)
  }

  const actions = document.createElement('div')
  actions.className = 'cq-actions'
  actions.append(
    button('Edit', 'btn-secondary', () => edit(body, actions, task)),
    button('Delete', 'btn-outline-dark', () => {
      if (confirm('Delete "' + task.title + '"?')) run(() => deleteTask({ id: task.id }))
    })
  )

  li.append(check, body, actions)
  return li
}

// Rename in place: the refresh waits until the edit is saved or cancelled
function edit (body, actions, task) {
  editing = true
  const box = document.createElement('div')
  box.className = 'cq-inline-edit'
  const input = document.createElement('input')
  input.className = 'form-control form-control-sm'
  input.value = task.title
  input.maxLength = 200
  const save = () => { editing = false; run(() => updateTask({ id: task.id, title: input.value })) }
  const cancel = () => { editing = false; refresh().catch(showError) }
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') save()
    if (e.key === 'Escape') cancel()
  })
  box.append(input)
  body.replaceChildren(box)
  actions.replaceChildren(button('Save', 'btn-primary', save), button('Cancel', 'btn-secondary', cancel))
  input.focus()
  input.select()
}

async function refresh () {
  const tasks = await listTasks({ status: 'all' })
  const open = tasks.filter((t) => !Number(t.done))
  const counts = { all: tasks.length, open: open.length, done: tasks.length - open.length }
  for (const [key, value] of Object.entries(counts)) {
    document.querySelector('[data-count="' + key + '"]').textContent = value
  }
  const shown = status === 'all' ? tasks : tasks.filter((t) => Boolean(Number(t.done)) === (status === 'done'))
  list.replaceChildren(...shown.map(row))
  empty.hidden = shown.length > 0
  empty.textContent = status === 'all' ? 'No tasks yet: add the first one above.' : 'No ' + status + ' tasks.'
}

form.addEventListener('submit', (event) => {
  event.preventDefault()
  const data = Object.fromEntries(new FormData(form))
  run(async () => {
    await createTask(data)
    form.reset()
    form.elements.title.focus()
  })
})

tabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    status = tab.dataset.status
    tabs.forEach((t) => {
      t.classList.toggle('active', t === tab)
      t.setAttribute('aria-selected', String(t === tab))
    })
    refresh().catch(showError)
  })
})

// Pick up changes made through REST or MCP (an AI agent) without reloading
refresh().catch(showError)
setInterval(() => { if (!document.hidden && !editing) refresh().catch(() => {}) }, 3000)
