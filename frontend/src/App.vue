<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import EntityDialog from './components/EntityDialog.vue'
import ProductDetailsDialog from './components/ProductDetailsDialog.vue'
import AppIcon from './components/AppIcon.vue'
import { api, allItems, type User, type Item, type Page, type Dashboard } from './api'

type View = 'dashboard' | 'shops' | 'products' | 'owners' | 'listings' | 'storefront'
const user = ref<User | null>(null)
const publicBrowsing = ref(false)
const ready = ref(false)
const email = ref('')
const password = ref('')
const loginBusy = ref(false)
const view = ref<View>('dashboard')
const error = ref('')
const notice = ref('')
const loading = ref(false)
const records = ref<Item[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const dashboard = ref<Dashboard | null>(null)
const shops = ref<Item[]>([])
const owners = ref<Item[]>([])
const products = ref<Item[]>([])
const selectedShop = ref<number | ''>('')
const selectedProduct = ref<Item | null>(null)
const modal = ref(false)
const editing = ref<Item | null>(null)
const saving = ref(false)
const formError = ref('')
const pendingDelete = ref<Item | null>(null)
const admin = computed(() => user.value?.permissions.includes('shops.manage') || false)
const can = (permission: string) => user.value?.permissions.includes(permission) || false
const money = (value?: string | number) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', maximumFractionDigits: 2 }).format(Number(value || 0))
const titles: Record<View, string> = { dashboard: 'Overview', shops: 'Shops', products: 'Shared catalog', owners: 'Shop owners', listings: 'Shop listings', storefront: 'All products' }
const descriptions: Record<View, string> = {
  dashboard: 'Summary of shops, products, and activity.',
  shops: 'Create shops and assign their owners.',
  products: 'Manage products available to all shops.',
  owners: 'Create and manage shop owner accounts.',
  listings: 'Manage products and pricing for each shop.',
  storefront: 'Browse products from all shops or filter the list by shop.',
}
const nav = computed(() => [
  { id: 'dashboard' as View, label: 'Overview', icon: 'dashboard' as const },
  ...(can('shops.view') ? [{ id: 'shops' as View, label: admin.value ? 'Shops' : 'My shops', icon: 'shop' as const }] : []),
  ...(can('catalog.view') ? [{ id: 'products' as View, label: 'Shared catalog', icon: 'catalog' as const }] : []),
  ...(can('listings.manage') ? [{ id: 'listings' as View, label: 'Shop listings', icon: 'list' as const }] : []),
  ...(can('owners.manage') ? [{ id: 'owners' as View, label: 'Shop owners', icon: 'users' as const }] : []),
])
const canCreate = computed(() => view.value === 'products' ? can('catalog.create') : view.value === 'owners' ? can('owners.manage') : view.value === 'shops' ? can('shops.manage') : view.value === 'listings' && !!selectedShop.value && can('listings.manage'))
const canEdit = (item: Item) => view.value === 'products' ? can('catalog.manage') || item.created_by === user.value?.id : view.value === 'shops' ? admin.value : view.value === 'owners' || view.value === 'listings'
const stats = computed(() => dashboard.value ? [
  { label: admin.value ? 'Total shops' : 'Your shops', value: dashboard.value.stats.shops },
  { label: 'Catalog products', value: dashboard.value.stats.products },
  { label: 'Shop listings', value: dashboard.value.stats.listings },
  { label: 'Active listings', value: dashboard.value.stats.active_listings },
] : [])
const isPublicStorefront = computed(() => view.value === 'storefront')
const currentShopName = computed(() => shops.value.find(shop => shop.id === Number(selectedShop.value))?.name)
function resetSession() { user.value = null; dashboard.value = null; records.value = []; modal.value = false; error.value = 'Your session has expired. Please sign in again.' }
async function login() {
  error.value = ''; loginBusy.value = true
  try {
    const result = await api<{ token: string; user: User }>('/login', 'POST', { email: email.value, password: password.value })
    sessionStorage.setItem('token', result.token); user.value = result.user; password.value = ''
    view.value = 'dashboard'; window.location.hash = 'dashboard'; await load()
  } catch (e) { error.value = (e as Error).message }
  finally { loginBusy.value = false }
}
async function logout() {
  loginBusy.value = true
  try { await api('/logout', 'POST'); sessionStorage.removeItem('token'); user.value = null; publicBrowsing.value = true; view.value = 'storefront'; records.value = []; dashboard.value = null; error.value = ''; notice.value = ''; window.location.hash = 'storefront'; await load() }
  catch (e) { error.value = (e as Error).message }
  finally { loginBusy.value = false }
}
async function load() {
  loading.value = true; error.value = ''
  try {
    if (view.value === 'dashboard') { dashboard.value = await api<Dashboard>('/dashboard'); return }
    if (view.value === 'listings') {
      shops.value = await allItems('/shops')
      if (!shops.value.some(s => s.id === Number(selectedShop.value))) selectedShop.value = shops.value[0]?.id || ''
      if (!selectedShop.value) { records.value = []; total.value = 0; lastPage.value = 1; return }
    }
    if (view.value === 'storefront') {
      shops.value = await api<Item[]>('/storefront/shops')
      if (selectedShop.value && !shops.value.some(shop => shop.id === Number(selectedShop.value))) selectedShop.value = ''
    }
    const path = view.value === 'listings' ? '/shops/' + selectedShop.value + '/listings' : '/' + (view.value === 'storefront' ? 'storefront' : view.value)
    const params = new URLSearchParams({ page: String(page.value) })
    if (view.value === 'products' && search.value) params.set('search', search.value)
    if (view.value === 'storefront' && selectedShop.value) params.set('shop_id', String(selectedShop.value))
    const result = await api<Page>(path + '?' + params.toString())
    records.value = result.data; lastPage.value = result.last_page; total.value = result.total
  } catch (e) { error.value = (e as Error).message }
  finally { loading.value = false }
}
async function navigate(next: View) {
  if (loading.value || saving.value) return
  view.value = next; page.value = 1; search.value = ''; notice.value = ''; pendingDelete.value = null
  if (next === 'storefront') selectedShop.value = ''
  window.location.hash = next
  await load()
}
async function openForm(item: Item | null = null) {
  editing.value = item; formError.value = ''; error.value = ''
  try {
    if (view.value === 'shops') owners.value = await allItems('/owners')
    if (view.value === 'listings' && !item) {
      const [catalog, assigned] = await Promise.all([allItems('/products'), allItems('/shops/' + selectedShop.value + '/listings')])
      products.value = catalog.filter(p => !assigned.some(l => l.product_id === p.id))
    }
    modal.value = true
  } catch (e) { error.value = (e as Error).message }
}
function entityPath() { return view.value === 'listings' ? '/shops/' + selectedShop.value + '/listings' : '/' + view.value }
async function save(data: Record<string, unknown>) {
  saving.value = true; formError.value = ''
  try {
    await api(entityPath() + (editing.value ? '/' + editing.value.id : ''), editing.value ? 'PUT' : 'POST', data)
    modal.value = false; notice.value = 'Changes saved successfully.'; await load()
  } catch (e) { formError.value = (e as Error).message }
  finally { saving.value = false }
}
async function remove() {
  if (!pendingDelete.value) return
  saving.value = true; error.value = ''
  try {
    await api(entityPath() + '/' + pendingDelete.value.id, 'DELETE')
    pendingDelete.value = null; notice.value = 'Deleted successfully.'
    if (records.value.length === 1 && page.value > 1) page.value--
    await load()
  } catch (e) { error.value = (e as Error).message; pendingDelete.value = null }
  finally { saving.value = false }
}
async function changePage(offset: number) { page.value += offset; await load() }
function allowedView(candidate: string): View {
  return candidate === 'storefront' || nav.value.some(n => n.id === candidate) ? candidate as View : 'dashboard'
}
async function browsePublic() {
  publicBrowsing.value = true
  if (view.value === 'storefront') { await load(); return }
  await navigate('storefront')
}
function returnToLogin() { publicBrowsing.value = false; error.value = ''; window.location.hash = 'login' }
function openWorkspace() {
  if (user.value) { void navigate('dashboard'); return }
  returnToLogin()
}
onMounted(async () => {
  window.addEventListener('session-expired', resetSession)
  try {
    if (sessionStorage.getItem('token')) {
      user.value = (await api<{ data: User }>('/me')).data
      const requestedView = window.location.hash.slice(1)
      view.value = requestedView ? allowedView(requestedView) : 'storefront'
      publicBrowsing.value = !requestedView
      await load()
    }
    else if (window.location.hash !== '#login') {
      publicBrowsing.value = true
      view.value = 'storefront'
      await load()
    }
  } catch (e) { error.value = (e as Error).message; sessionStorage.removeItem('token') }
  finally { ready.value = true }
})
onUnmounted(() => window.removeEventListener('session-expired', resetSession))
</script>

<template>
  <div v-if="!ready" class="boot" role="status">Opening your workspace…</div>
  <main v-else-if="!user && !publicBrowsing" class="login-layout">
    <a href="#dashboard" class="brand"><span class="brand-mark">M</span> Merchant</a>
    <section class="login-card">
      <div class="login-form">
        <h1>Sign in</h1><p class="muted">Enter your account details to continue.</p>
        <form @submit.prevent="login">
          <p v-if="error" class="alert" role="alert">{{ error }}</p>
          <label>Email address<input v-model="email" type="email" placeholder="you@example.com" required autocomplete="username" autofocus /></label>
          <label>Password<input v-model="password" type="password" placeholder="Enter your password" required autocomplete="current-password" /></label>
          <button class="button login-submit" :disabled="loginBusy">{{ loginBusy ? 'Signing in…' : 'Sign in' }}</button>
        </form>
        <p class="login-help">Need an account? Contact your administrator.</p>
        <button class="text-button public-browse" @click="browsePublic">View customer storefront</button>
      </div>
    </section>
  </main>
  <div v-else-if="isPublicStorefront" class="storefront-site">
    <header class="public-header">
      <div class="public-header-inner">
        <a href="/" class="public-brand" @click.prevent="browsePublic"><span class="public-brand-mark">M</span><span>Merchant</span></a>
        <nav class="public-navigation" aria-label="Store navigation"><a href="#products">Products</a><a href="#shop-filter">Browse shops</a></nav>
        <button class="button public-login" @click="openWorkspace">{{ user ? 'Dashboard' : 'Sign in' }}</button>
      </div>
    </header>

    <main class="store-main">
      <section class="store-hero">
        <p class="store-eyebrow">INDEPENDENT SHOPS · ONE COLLECTION</p>
        <h1>Products from local shops</h1>
        <p>Browse the latest products and choose a shop to narrow your search.</p>
      </section>

      <section id="products" class="store-collection">
        <div class="store-collection-heading"><div><p>SHOP THE COLLECTION</p><h2>{{ selectedShop ? currentShopName : 'All products' }}</h2></div><span>{{ total }} {{ total === 1 ? 'product' : 'products' }}</span></div>
        <div class="store-toolbar">
          <label id="shop-filter" class="store-filter">Filter by shop<select v-model="selectedShop" aria-label="Filter products by shop" @change="page = 1; load()"><option value="">All shops</option><option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option></select></label>
          <span v-if="currentShopName" class="store-filter-note">Showing products from {{ currentShopName }}</span>
        </div>
        <p v-if="error" class="alert" role="alert">{{ error }} <button @click="load">Try again</button></p>
        <div v-if="loading" class="store-loading" role="status">Loading products…</div>
        <div v-else-if="!records.length" class="store-empty"><AppIcon name="catalog" :size="32" /><h3>No products available</h3><p>{{ selectedShop ? 'This shop has no active products right now.' : 'Active shop listings will appear here.' }}</p></div>
        <div v-else class="product-grid public-products"><button v-for="(item, index) in records" :key="item.id" type="button" class="product-card product-card-trigger" aria-haspopup="dialog" :aria-label="`View details for ${item.name}`" @click="selectedProduct = item"><div class="product-image" :class="'tone-' + index % 3"><img v-if="item.thumbnail" :src="item.thumbnail" :alt="item.name" loading="lazy" @error="($event.target as HTMLImageElement).style.display = 'none'" /><AppIcon v-else name="package" :size="36" /><span class="image-label">{{ item.shop?.name }}</span></div><div class="product-body"><div class="product-heading"><h2>{{ item.name }}</h2><strong>{{ money(item.price) }}</strong></div><p>{{ item.description || 'No description available.' }}</p><div class="product-footer"><small>{{ item.shop?.name }}</small><span class="product-view-hint">View details</span></div></div></button></div>
        <div v-if="!loading && lastPage > 1" class="pagination"><button class="button secondary" :disabled="page <= 1" @click="changePage(-1)">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button class="button secondary" :disabled="page >= lastPage" @click="changePage(1)">Next</button></div>
      </section>
    </main>
    <footer class="public-footer"><span class="public-brand"><span class="public-brand-mark">M</span><span>Merchant</span></span><span>Shop products from independent stores.</span><button class="text-button" @click="openWorkspace">{{ user ? 'Open dashboard' : 'Sign in' }}</button></footer>
    <ProductDetailsDialog v-if="selectedProduct" :item="selectedProduct" @close="selectedProduct = null" />
  </div>
  <div v-else class="workspace">
    <aside v-if="user" class="sidebar">
      <a href="#dashboard" class="brand" @click.prevent="navigate('dashboard')"><span class="brand-mark">m</span> merchant<span class="brand-dot">.</span></a>
      <p class="nav-label">WORKSPACE</p>
      <nav aria-label="Main navigation"><button v-for="item in nav" :key="item.id" :class="{ active: view === item.id }" :disabled="loading" @click="navigate(item.id)"><span class="nav-icon"><AppIcon :name="item.icon" :size="17" /></span>{{ item.label }}<span v-if="view === item.id" class="nav-active-dot" aria-hidden="true">•</span></button></nav>
      <button class="store-link" :class="{ active: view === 'storefront' }" @click="navigate('storefront')"><AppIcon name="external" :size="15" /> Customer view</button>
      <div class="account"><span class="avatar">{{ user.name.charAt(0) }}</span><div><strong>{{ user.name }}</strong><small>{{ admin ? 'Administrator' : 'Shop owner' }}</small></div><button class="icon-button" aria-label="Sign out" title="Sign out" :disabled="loginBusy" @click="logout">⇥</button></div>
    </aside>
    <div class="main-column">
      <header class="topbar"><span>merchant. <span class="slash">/</span> <strong>{{ titles[view] }}</strong></span><span v-if="user" class="role-pill"><span></span>{{ admin ? 'Admin workspace' : 'Shop owner workspace' }}</span><button v-else class="text-button" @click="returnToLogin">Sign in to workspace ↗</button></header>
      <main class="content">
        <div class="page-heading"><div><h1>{{ view === 'dashboard' ? 'Dashboard' : titles[view] }}</h1><p class="muted">{{ descriptions[view] }}</p></div><button v-if="canCreate" class="button" @click="openForm()">{{ view === 'products' ? 'Add product' : view === 'owners' ? 'Add owner' : view === 'shops' ? 'Add shop' : 'Add product to shop' }}</button></div>
        <p v-if="error" class="alert" role="alert">{{ error }} <button @click="load">Try again</button></p>
        <p v-if="notice" class="success" role="status">{{ notice }}<button aria-label="Dismiss" @click="notice = ''">×</button></p>
        <div v-if="loading" class="loading" role="status">Loading workspace…</div>
        <template v-else-if="view === 'dashboard' && dashboard">
          <section class="stats-grid" aria-label="Workspace statistics"><article v-for="stat in stats" :key="stat.label"><span>{{ stat.label }}</span><strong>{{ stat.value ?? 0 }}</strong></article></section>
          <div class="dashboard-grid">
            <section class="panel"><div class="panel-heading"><div><h2>{{ admin ? 'Recent shops' : 'Your shops' }}</h2><p>Shops assigned to your account.</p></div><button class="text-button" @click="navigate('shops')">View all</button></div><div v-if="!dashboard.shops.length" class="empty">No shops assigned yet.</div><button v-for="shop in dashboard.shops" :key="shop.id" class="shop-row" @click="selectedShop = shop.id; navigate('listings')"><span class="shop-monogram">{{ shop.name.charAt(0) }}</span><span class="shop-row-copy"><strong>{{ shop.name }}</strong><small>{{ shop.owner?.name }} · {{ shop.listings_count }} listings</small></span><span class="badge" :class="{ inactive: !shop.is_active }">{{ shop.is_active ? 'Active' : 'Inactive' }}</span><span aria-hidden="true"><AppIcon name="external" :size="14" /></span></button></section>
            <section class="panel"><div class="panel-heading"><div><h2>Recent products</h2><p>Latest products in the shared catalog.</p></div><button class="text-button" @click="navigate('products')">View all</button></div><div v-if="!dashboard.recent_products.length" class="empty">No products found.</div><div v-for="(product, index) in dashboard.recent_products" :key="product.id" class="catalog-row"><span class="product-placeholder" :class="'tone-' + index % 3"><img v-if="product.thumbnail" :src="product.thumbnail" alt="" @error="($event.target as HTMLImageElement).style.display = 'none'" /><AppIcon v-else name="package" :size="19" /></span><span class="shop-row-copy"><strong>{{ product.name }}</strong><small>{{ product.creator?.name || 'Shared catalog' }}</small></span><strong class="price">{{ money(product.price) }}</strong></div></section>
          </div>
        </template>
        <template v-else>
          <div class="collection-toolbar">
            <form v-if="view === 'products'" class="search" @submit.prevent="page = 1; load()"><input v-model="search" aria-label="Search products" placeholder="Search the catalog…" /><button class="button secondary" type="submit">Search</button></form>
            <label v-if="view === 'listings' || view === 'storefront'" class="shop-select">Filter by shop<select v-model="selectedShop" :aria-label="view === 'storefront' ? 'Filter products by shop' : 'Select shop'" @change="page = 1; load()"><option v-if="view === 'storefront'" value="">All shops</option><option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option></select></label>
            <span class="muted">{{ total }} {{ view === 'storefront' ? 'available listings' : 'records' }}</span>
          </div>
          <div v-if="!records.length" class="panel empty large-empty"><span><AppIcon name="catalog" :size="34" /></span><h2>{{ view === 'storefront' ? 'No products available' : view === 'listings' && !selectedShop ? 'No assigned shops yet' : 'No records found' }}</h2><p>{{ view === 'storefront' ? (selectedShop ? 'This shop has no active products right now.' : 'Active products assigned to shops will appear here.') : view === 'listings' ? 'Select a shop and add a product from the shared catalog. Your administrator can assign shops to you.' : 'There are no records to show here yet.' }}</p><button v-if="canCreate" class="button" @click="openForm()">Create your first {{ view === 'owners' ? 'owner' : view === 'shops' ? 'shop' : view === 'products' ? 'product' : 'listing' }}</button></div>
          <div v-else-if="view === 'products' || view === 'storefront'" class="product-grid"><article v-for="(item, index) in records" :key="item.id" class="product-card"><div class="product-image" :class="'tone-' + index % 3"><img v-if="item.thumbnail" :src="item.thumbnail" :alt="item.name" loading="lazy" @error="($event.target as HTMLImageElement).style.display = 'none'" /><span v-else>▧</span><span class="image-label">{{ view === 'storefront' ? item.shop?.name : item.sku }}</span></div><div class="product-body"><div class="product-heading"><h2>{{ item.name }}</h2><strong>{{ money(item.price) }}</strong></div><p>{{ item.description || 'No description added yet.' }}</p><div class="product-footer"><small>{{ view === 'storefront' ? item.shop?.name : item.creator?.name || 'Shared catalog' }}</small><div v-if="view === 'products' && canEdit(item)" class="row-actions"><button @click="openForm(item)">Edit</button><button class="danger-text" @click="pendingDelete = item">Delete</button></div><small v-else-if="view === 'products'">{{ item.listings_count }} listings</small></div></div></article></div>
          <section v-else class="panel table-wrap"><table><thead><tr><th>{{ view === 'owners' ? 'Shop owner' : view === 'shops' ? 'Shop' : 'Product listing' }}</th><th>{{ view === 'owners' ? 'Email' : view === 'shops' ? 'Owner' : 'Price' }}</th><th>{{ view === 'owners' ? 'Assigned shops' : 'Status' }}</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody><tr v-for="item in records" :key="item.id"><td><strong>{{ item.name }}</strong><small>{{ view === 'shops' ? item.slug : view === 'listings' ? item.product?.sku : 'Shop owner' }}</small></td><td>{{ view === 'owners' ? item.email : view === 'shops' ? item.owner?.name : money(item.price) }}</td><td><span v-if="view === 'owners'">{{ item.shops_count }}</span><span v-else class="badge" :class="{ inactive: !item.is_active }">{{ item.is_active ? 'Active' : 'Inactive' }}</span></td><td class="row-actions"><button v-if="view === 'shops'" @click="selectedShop = item.id; navigate('listings')">Listings ↗</button><template v-if="canEdit(item)"><button @click="openForm(item)">Edit</button><button class="danger-text" @click="pendingDelete = item">Delete</button></template></td></tr></tbody></table></section>
          <div v-if="lastPage > 1" class="pagination"><button class="button secondary" :disabled="page <= 1" @click="changePage(-1)">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button class="button secondary" :disabled="page >= lastPage" @click="changePage(1)">Next</button></div>
        </template>
        <footer class="workspace-footer"><span>Merchant management</span></footer>
      </main>
    </div>
    <EntityDialog v-if="modal" :kind="view" :item="editing" :owners="owners" :products="products" :busy="saving" :error="formError" @close="modal = false" @save="save" />
    <div v-if="pendingDelete" class="confirm-overlay" @keydown.esc="!saving && (pendingDelete = null)"><section role="alertdialog" aria-modal="true" aria-labelledby="delete-title" class="confirm-box"><p class="eyebrow">PLEASE CONFIRM</p><h2 id="delete-title">Delete {{ pendingDelete.name }}?</h2><p>{{ view === 'shops' ? 'This will also remove every listing in this shop.' : 'This record will be permanently removed.' }}</p><div class="dialog-actions"><button class="button secondary" :disabled="saving" @click="pendingDelete = null">Cancel</button><button class="button danger" :disabled="saving" @click="remove">{{ saving ? 'Deleting…' : 'Delete' }}</button></div></section></div>
  </div>
</template>



