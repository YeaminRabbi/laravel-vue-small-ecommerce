<script setup lang="ts">
import { onMounted, ref } from 'vue'
import type { Item } from '../api'
const props = defineProps<{ kind: string; item: Item | null; owners: Item[]; products: Item[]; busy: boolean; error: string }>()
const emit = defineEmits<{ close: []; save: [Record<string, unknown>] }>()
const dialog = ref<HTMLDialogElement>()
const form = ref<Record<string, any>>({
  name: '', description: '', price: '', thumbnail: '', sku: '', slug: '', owner_id: '',
  email: '', password: '', product_id: '', is_active: true,
  ...props.item,
})
onMounted(() => dialog.value?.showModal())
function chooseProduct() {
  const product = props.products.find(p => p.id === Number(form.value.product_id))
  if (product) Object.assign(form.value, { name: product.name, description: product.description || '', price: product.price, thumbnail: product.thumbnail || '' })
}
function submit() {
  const keys = props.kind === 'owners' ? ['name', 'email', 'password']
    : props.kind === 'shops' ? ['name', 'slug', 'owner_id', 'description', 'is_active']
    : props.kind === 'products' ? ['name', 'sku', 'description', 'price', 'thumbnail']
    : ['name', 'description', 'price', 'thumbnail', 'is_active', ...(props.item ? [] : ['product_id'])]
  const data = Object.fromEntries(keys.map(k => [k, form.value[k] === '' && ['thumbnail', 'description', 'password'].includes(k) ? null : form.value[k]]))
  emit('save', data)
}
</script>

<template>
  <dialog ref="dialog" aria-labelledby="dialog-title" @cancel.prevent="!busy && emit('close')" @click="(e) => { if (e.target === dialog && !busy) emit('close') }">
    <form @submit.prevent="submit">
      <div class="dialog-heading"><div><p class="eyebrow">WORKSPACE</p><h2 id="dialog-title">{{ item ? 'Edit' : 'Create' }} {{ kind === 'owners' ? 'shop owner' : kind === 'shops' ? 'shop' : kind === 'products' ? 'catalog product' : 'shop listing' }}</h2></div><button type="button" class="icon-button" aria-label="Close" :disabled="busy" @click="emit('close')">×</button></div>
      <p v-if="error" class="alert" role="alert">{{ error }}</p>
      <label v-if="kind === 'listings' && !item">Shared catalog product
        <select aria-label="Shared catalog product" v-model="form.product_id" required @change="chooseProduct"><option value="" disabled>Select a product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} · {{ p.sku }}</option></select>
      </label>
      <label>Name<input v-model="form.name" required maxlength="255" autofocus /></label>
      <template v-if="kind === 'owners'">
        <label>Email<input v-model="form.email" required type="email" maxlength="255" /></label>
        <label>{{ item ? 'New password (leave blank to keep)' : 'Password' }}<input v-model="form.password" :required="!item" type="password" minlength="8" maxlength="255" autocomplete="new-password" /></label>
        <p class="muted">This account receives the Shop owner role. Owners manage listings in their assigned shops.</p>
      </template>
      <template v-else>
        <template v-if="kind === 'shops'">
          <label>Shop owner<select aria-label="Shop owner" v-model="form.owner_id" required><option disabled value="">Assign an owner</option><option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }} · {{ owner.email }}</option></select></label>
          <label>URL slug<input v-model="form.slug" required pattern="[a-zA-Z0-9_-]+" placeholder="my-shop" maxlength="255" /></label>
        </template>
        <label v-if="kind === 'products'">SKU<input v-model="form.sku" required maxlength="100" placeholder="HOME-001" /></label>
        <label>Description<textarea v-model="form.description" rows="3" maxlength="10000"></textarea></label>
        <template v-if="kind !== 'shops'">
          <div class="form-row"><label>Price (BDT)<input v-model="form.price" required type="number" min="0" max="9999999999.99" step="0.01" /></label></div>
          <label>Thumbnail URL<input v-model="form.thumbnail" type="url" pattern="https?://.+" maxlength="2048" placeholder="https://example.com/image.jpg" /></label>
          <p v-if="kind === 'listings'" class="muted">These details apply only to this shop. The shared catalog stays unchanged.</p>
        </template>
        <label v-if="kind !== 'products'" class="checkbox"><input v-model="form.is_active" type="checkbox" /> Active and visible to customers</label>
      </template>
      <div class="dialog-actions"><button type="button" class="button secondary" :disabled="busy" @click="emit('close')">Cancel</button><button class="button" :disabled="busy">{{ busy ? 'Saving…' : 'Save changes' }}</button></div>
    </form>
  </dialog>
</template>


