<script setup lang="ts">
import { onMounted, ref } from 'vue'
import type { Item } from '../api'
import AppIcon from './AppIcon.vue'

defineProps<{ item: Item }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const imageFailed = ref(false)
const money = (value?: string | number) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', maximumFractionDigits: 2 }).format(Number(value || 0))

onMounted(() => dialog.value?.showModal())

function close() {
  dialog.value?.close()
  emit('close')
}

function closeOnBackdrop(event: MouseEvent) {
  if (event.target === dialog.value) close()
}
</script>

<template>
  <dialog ref="dialog" class="product-dialog" aria-labelledby="product-detail-title" @cancel.prevent="close" @click="closeOnBackdrop">
    <section class="product-detail-layout">
      <div class="product-detail-image">
        <img v-if="item.thumbnail && !imageFailed" :src="item.thumbnail" :alt="item.name" @error="imageFailed = true" />
        <AppIcon v-else name="package" :size="44" />
      </div>
      <div class="product-detail-copy">
        <div class="product-detail-topline">
          <div>
            <p class="store-eyebrow">SHOP PRODUCT</p>
            <p class="product-detail-shop">{{ item.shop?.name || 'Independent shop' }}</p>
          </div>
          <button type="button" class="product-detail-close" aria-label="Close product details" @click="close">×</button>
        </div>
        <h2 id="product-detail-title">{{ item.name }}</h2>
        <p class="product-detail-price">{{ money(item.price) }}</p>
        <section class="product-detail-description" aria-label="Product description">
          <h3>Product details</h3>
          <p>{{ item.description || 'No description available.' }}</p>
        </section>
        <div class="product-detail-actions">
          <button type="button" class="button secondary" @click="close">Close</button>
        </div>
      </div>
    </section>
  </dialog>
</template>
