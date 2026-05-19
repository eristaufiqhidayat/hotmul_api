## Testisbbbb
## 🔑 Cara Pasti Supaya Bisa Push

### 🔹 Opsi 1: Pakai Personal Access Token (paling cepat)

1. Buka GitHub → **Settings** → **Developer settings** → **Personal access tokens → Tokens (classic)**.

2. Klik **Generate new token**.

   * Expiration: pilih 90 hari atau *no expiration*.
   * Scope: centang **repo**.

3. Copy token yang keluar (misalnya `ghp_xxxABC123...`).

4. Ganti remote kamu pakai token langsung:

   ```bash
   git remote set-url origin https://<TOKEN>@github.com/eristaufiqhidayat/apihotmulquran.git
   ```

   contoh:

   ```bash
   git remote set-url origin https://ghp_xxxABC123@github.com/eristaufiqhidayat/apihotmulquran.git
   ```

5. Push ulang:

   ```bash
   git push -u origin main
   ```

👉 Dengan cara ini, Git langsung pakai token, jadi tidak tanya username/password lagi.

---

### 🔹 Opsi 2: Pakai SSH Key (lebih aman jangka panjang)

1. Buat key:

   ```bash
   ssh-keygen -t ed25519 -C "email_github_kamu"
   ```

2. Tambah ke agent:

   ```bash
   eval "$(ssh-agent -s)"
   ssh-add ~/.ssh/id_ed25519
   ```

3. Copy isi public key:

   ```bash
   cat ~/.ssh/id_ed25519.pub
   ```

   Tambahkan ke GitHub → **Settings → SSH and GPG keys**.

4. Ganti remote ke SSH:

   ```bash
   git remote set-url origin git@github.com:eristaufiqhidayat/apihotmulquran.git
   ```

5. Push:

   ```bash
   git push -u origin main
   ```

---

⚡ Rekomendasi: Kalau butuh cepat → pakai **Opsi 1 dengan token**. Kalau jangka panjang biar nyaman → pakai **SSH Key**.
hhh