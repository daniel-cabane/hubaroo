<script setup>
import { Primitive } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  asChild: { type: Boolean, required: false },
  as: { type: String, required: false, default: 'label' },
  htmlFor: { type: String, required: false },
});

const labelClass = cn(
  'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
  props.class,
);
</script>

<template>
  <Primitive
    data-slot="label"
    :as="as"
    :as-child="asChild"
    :for="htmlFor"
    :class="labelClass"
  >
    <slot />
  </Primitive>
</template>
