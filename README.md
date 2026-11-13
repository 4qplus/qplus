# qplus

Windows IIS + PHP 기반의 가볍고 직관적인 셀프 호스팅 파일 공유 스토리지 시스템입니다.  
설치 마법사(`qplus_setup.exe`)를 통해 웹 루트 및 파일 저장소 경로 지정, IIS/FastCGI 자동 세팅, 네트워크 구성(로컬 / 포트포워딩+DDNS / Cloudflare Tunnel / 사설 VPN)을 한 번에 완료할 수 있습니다.

---

## 주요 기능
- **모던 웹 UI**: 흑백 + 딥 스카이블루 포인트 디자인, 폴더/파일 탐색, 검색, 미리보기
- **대용량 파일 업로드/다운로드**: 드래그 앤 드롭 업로드, 다중 파일 ZIP 다운로드, 폴더별 비밀번호 보호
- **관리자 / 게스트 권한 분리**: Bcrypt(`password_hash`) 기반 비밀번호 관리 및 실시간 접속 상태 표시
- **보안 기본 적용**: 경로 탐색(Path Traversal) 차단, `web.config` 요청 필터링, `php.ini` 보안 강화
- **원클릭 설치 마법사 (`qplus_setup.exe`)**:
  - 프로그램 설치 경로 및 공유 파일 저장소 경로(`directory`) 자유 지정
  - **로컬 네트워크 전용(LAN)**: Windows 방화벽 `LocalSubnet` 제한 자동 구성
  - **포트포워딩 + DDNS**: 방화벽 허용 + 5분 주기 DDNS 자동 갱신 스케줄러 등록
  - **Cloudflare Tunnel**: `cloudflared` 서비스 자동 연동
  - **사설 VPN**: Tailscale / WireGuard 접속 및 VPN IP 자동 감지

---

## 설치 방법 (Windows)
1. GitHub [**Releases**](../../releases) 페이지에서 최신 `qplus_setup.exe`를 다운로드합니다.
2. `qplus_setup.exe`를 실행합니다. *(관리자 권한 UAC 프롬프트가 표시됩니다.)*
   - 코드 서명이 없는 오픈소스 빌드이므로 Windows SmartScreen 경고가 뜨면 **추가 정보 → 실행**을 클릭하세요.
   - 필요 시 함께 제공되는 `.sha256` 파일로 무결성을 검증할 수 있습니다.
3. 설치 경로, 저장소 경로, 초기 관리자/게스트 비밀번호, 네트워크 방식을 선택한 뒤 **설치 시작**을 누릅니다.

> **요구 사항**: Windows 10/11 또는 Windows Server (x64), Visual C++ 재배포 가능 패키지(x64, PHP 실행용)

---

## 소스에서 직접 빌드하기
```powershell
# 1. 공식 PHP Windows x64 NTS ZIP을 내려받아 ./php 폴더에 압축 해제
# 2. 인스톨러 빌드 스크립트 실행
powershell -NoProfile -ExecutionPolicy Bypass -File .\installer\build_installer.ps1
```
빌드가 완료되면 `installer\qplus_setup.exe`가 생성됩니다.

---

## 라이선스 (License)
- `qplus` 소스 코드는 **GNU General Public License v2.0 (GPL-2.0)**에 따라 자유롭게 사용, 수정 및 배포할 수 있습니다. 자세한 내용은 [LICENSE](./LICENSE)를 참고하세요.
- 배포용 인스톨러에 포함되는 PHP 런타임은 별도의 공식 바이너리이며 PHP License v3.01을 따릅니다. 자세한 내용은 [THIRD-PARTY-NOTICES.md](./THIRD-PARTY-NOTICES.md)를 참고하세요.

