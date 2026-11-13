# qplus

> Lightweight, self-hosted web file storage for Windows.

A fast, private cloud storage solution built on Windows IIS and PHP — zero database required.

---

### Features

- **Minimalist UI** — Clean, modern, responsive web file manager with drag-and-drop.
- **Reliable Transfers** — Chunked uploads and HTTP Range resume support for large files.
- **Zero Database** — Pure flat-file architecture, easy to back up and migrate.
- **Folder Protection** — Password-protect sensitive directories with a single click.
- **Flexible Network** — Works seamlessly with Local LAN, DDNS, Cloudflare Tunnel, or Tailscale/VPN.
- **Bilingual Support** — English and 한국어 supported out of the box (English default).

---

### Quick Start

1. Download **`qplus_setup.exe`** from [**Releases**](../../releases).
2. Run the installer as Administrator.
3. Choose your settings and passwords, then click **Install**.

> [!NOTE]
> Requires [Visual C++ 2015-2022 Redistributable (x64)](https://aka.ms/vs/17/release/vc_redist.x64.exe).

---

### Build from Source

```powershell
powershell -ExecutionPolicy Bypass -File .\installer\build_installer.ps1
```

---

### License

[GPL-2.0](./LICENSE)
