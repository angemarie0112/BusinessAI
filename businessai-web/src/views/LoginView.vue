<script setup>
import { reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
  email: '',
  password: '',
})

const handleLogin = async () => {
  const success = await auth.login(form)

  if (success) {
    router.push('/dashboard')
  }
}
</script>

<template>
  <main class="auth-page">
    <!-- LEFT SIDE -->
    <section class="auth-panel">
      <div class="brand">
        <div class="brand-mark">B</div>
        <span>BusinessAI</span>
      </div>

      <div class="auth-content">
        <div class="auth-heading">
          <p class="eyebrow">WELCOME BACK</p>

          <h1>Sign in to BusinessAI</h1>

          <p>
            Continue working with your documents and turn business
            information into useful insights.
          </p>
        </div>

        <form class="auth-form" @submit.prevent="handleLogin">
          <div class="field">
            <label for="email">Email address</label>

            <input
              id="email"
              v-model="form.email"
              type="email"
              placeholder="you@example.com"
              autocomplete="email"
            />

            <p v-if="auth.errors.email" class="field-error">
              {{ auth.errors.email[0] }}
            </p>
          </div>

          <div class="field">
            <label for="password">Password</label>

            <input
              id="password"
              v-model="form.password"
              type="password"
              placeholder="Enter your password"
              autocomplete="current-password"
            />

            <p v-if="auth.errors.password" class="field-error">
              {{ auth.errors.password[0] }}
            </p>
          </div>

          <button
            type="submit"
            class="primary-button"
            :disabled="auth.loading"
          >
            {{ auth.loading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>

        <p class="auth-switch">
          Don't have an account?
          <RouterLink to="/register">Create account</RouterLink>
        </p>
      </div>

      <p class="auth-footer">
        AI-powered document intelligence for business.
      </p>
    </section>

    <!-- RIGHT SIDE -->
    <section class="visual-panel">
      <div class="visual-glow"></div>

      <div class="visual-content">
        <p class="eyebrow visual-eyebrow">
          BUSINESS INTELLIGENCE
        </p>

        <h2>
          Your documents.
          <br />
          <span>Useful answers.</span>
        </h2>

        <p class="visual-description">
          BusinessAI helps you explore business documents through intelligent
          search, analysis and AI-assisted answers.
        </p>

        <div class="process-card">
          <div class="process-row">
            <div class="process-icon">01</div>

            <div>
              <strong>Upload</strong>
              <p>Add your business documents.</p>
            </div>
          </div>

          <div class="process-line"></div>

          <div class="process-row">
            <div class="process-icon">02</div>

            <div>
              <strong>Analyze</strong>
              <p>BusinessAI processes their content.</p>
            </div>
          </div>

          <div class="process-line"></div>

          <div class="process-row">
            <div class="process-icon active">03</div>

            <div>
              <strong>Ask</strong>
              <p>Get answers grounded in your documents.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(420px, 0.9fr);
  background: #ffffff;
  color: #0f172a;
}

.auth-panel {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 40px clamp(32px, 6vw, 96px);
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 20px;
  font-weight: 750;
  letter-spacing: -0.03em;
}

.brand-mark {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: #2563eb;
  color: white;
  font-size: 17px;
}

.auth-content {
  width: 100%;
  max-width: 480px;
  margin: auto;
  padding: 60px 0;
}

.eyebrow {
  margin: 0 0 14px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 750;
  letter-spacing: 0.16em;
}

.auth-heading h1 {
  margin: 0;
  font-size: clamp(38px, 4vw, 52px);
  line-height: 1.05;
  letter-spacing: -0.045em;
}

.auth-heading > p:last-child {
  margin: 20px 0 0;
  color: #64748b;
  font-size: 16px;
  line-height: 1.7;
}

.auth-form {
  display: grid;
  gap: 20px;
  margin-top: 38px;
}

.field {
  display: grid;
  gap: 8px;
}

.field label {
  font-size: 14px;
  font-weight: 650;
}

.field input {
  width: 100%;
  height: 52px;
  padding: 0 16px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
  font: inherit;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.field input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.field-error {
  margin: 0;
  color: #dc2626;
  font-size: 13px;
}

.primary-button {
  height: 54px;
  margin-top: 4px;
  border: 0;
  border-radius: 10px;
  background: #0f172a;
  color: white;
  font: inherit;
  font-weight: 650;
  cursor: pointer;
  transition:
    background 0.2s,
    transform 0.2s;
}

.primary-button:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
}

.primary-button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.auth-switch {
  margin: 26px 0 0;
  color: #64748b;
  font-size: 14px;
  text-align: center;
}

.auth-switch a {
  color: #2563eb;
  font-weight: 650;
  text-decoration: none;
}

.auth-footer {
  margin: 0;
  color: #94a3b8;
  font-size: 12px;
}

.visual-panel {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  display: flex;
  align-items: center;
  padding: clamp(50px, 7vw, 100px);
  background: #0f172a;
  color: white;
}

.visual-glow {
  position: absolute;
  width: 500px;
  height: 500px;
  right: -150px;
  top: -100px;
  border-radius: 50%;
  background: rgba(37, 99, 235, 0.22);
  filter: blur(80px);
}

.visual-content {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 520px;
}

.visual-eyebrow {
  color: #60a5fa;
}

.visual-content h2 {
  margin: 0;
  font-size: clamp(44px, 5vw, 68px);
  line-height: 1.02;
  letter-spacing: -0.05em;
}

.visual-content h2 span {
  color: #60a5fa;
}

.visual-description {
  max-width: 470px;
  margin: 26px 0 0;
  color: #94a3b8;
  font-size: 16px;
  line-height: 1.75;
}

.process-card {
  margin-top: 52px;
  padding: 28px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.04);
  backdrop-filter: blur(12px);
}

.process-row {
  display: flex;
  align-items: center;
  gap: 18px;
}

.process-row strong {
  font-size: 15px;
}

.process-row p {
  margin: 4px 0 0;
  color: #94a3b8;
  font-size: 13px;
}

.process-icon {
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border: 1px solid #334155;
  border-radius: 50%;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 700;
}

.process-icon.active {
  border-color: #3b82f6;
  background: #2563eb;
  color: white;
}

.process-line {
  width: 1px;
  height: 24px;
  margin: 6px 0 6px 21px;
  background: #334155;
}

@media (max-width: 900px) {
  .auth-page {
    grid-template-columns: 1fr;
  }

  .visual-panel {
    display: none;
  }

  .auth-panel {
    padding: 28px 24px;
  }

  .auth-content {
    max-width: 520px;
  }
}
</style>