<template>
  <div class="min-h-dvh bg-surface bg-grain flex items-center justify-center p-4 relative overflow-hidden">
    <!-- ambient depth -->
    <div
      class="absolute -top-40 -right-40 w-[28rem] h-[28rem] rounded-full pointer-events-none"
      style="background: radial-gradient(closest-side, rgba(0,181,189,0.10), transparent 70%)"
      aria-hidden="true"
    />
    <div
      class="absolute -bottom-32 -left-32 w-[22rem] h-[22rem] rounded-full pointer-events-none"
      style="background: radial-gradient(closest-side, rgba(255,210,140,0.07), transparent 70%)"
      aria-hidden="true"
    />
    <div class="relative w-full flex justify-center">
      <slot />
    </div>
  </div>
</template>
