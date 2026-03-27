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
  as: { type: String, required: false, default: 'input' },
  type: { type: String, required: false, default: 'text' },
});

const inputClass = cn(
  'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
  props.class,
);
</script>

<template>
  <Primitive
    data-slot="input"
    :as="as"
    :as-child="asChild"
    :type="type"
    :class="inputClass"
    v-bind="$attrs"
  />
</template>
