<template>
  <div class="empty-state-container">
    <div class="empty-state-icon">
      <svg 
        xmlns="http://www.w3.org/2000/svg" 
        width="64" 
        height="64" 
        viewBox="0 0 24 24" 
        fill="none" 
        stroke="currentColor" 
        stroke-width="1.5" 
        stroke-linecap="round" 
        stroke-linejoin="round"
        class="icon-cloud-exclamation">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
        <path d="M15 18.004h-8.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.374 0 2.562 .805 3.121 1.972"></path>
        <path d="M19 16v3"></path>
        <path d="M19 22v.01"></path>
      </svg>
    </div>
    
    <div class="empty-state-message">
      {{ formattedMessage }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'EmptyState',
  props: {
    message: {
      type: String,
      default: "No hay datos disponibles"
    }
  },
  computed: {
    formattedMessage() {
      // Reemplazar guiones por espacios para mejor legibilidad
      return this.message.replace(/-/g, ' ');
    }
  }
};
</script>

<style scoped>
.empty-state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  min-height: 300px;
  text-align: center;
}

.empty-state-icon {
  margin-bottom: 20px;
  color: #9ca3af;
  opacity: 0.8;
  animation: fadeIn 0.5s ease-in;
}

.icon-cloud-exclamation {
  width: 64px;
  height: 64px;
  stroke: currentColor;
}

.empty-state-message {
  font-size: 16px;
  color: #6b7280;
  font-weight: 500;
  line-height: 1.5;
  max-width: 400px;
  animation: fadeIn 0.5s ease-in 0.1s both;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

</style>