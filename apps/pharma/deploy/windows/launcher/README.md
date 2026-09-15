# Desktop shell (app window)

## Current (ships today)

`BprooPharma.vbs` → `BprooPharmaHost.ps1`:

1. Starts portable PHP **without a console** (`php-win.exe` + `CreateNoWindow`)
2. Waits until `http://127.0.0.1:8003` answers
3. Opens **Edge or Chrome in `--app=` mode** (no address bar / tabs — looks like a desktop app)
4. Dedicated profile under `storage/app/desktop/webview-profile`
5. When the window closes → stops PHP

Shortcuts (Inno + desktop) point at `BprooPharma.vbs` so nothing flashes in a terminal.

Fallback: `Start-BprooPharma.ps1` (console) if debugging.

## Next (optional native WebView2)

When a build machine has the **.NET SDK**, we can compile a small WinForms/WPF host under `launcher/` that embeds WebView2 instead of Edge `--app=`. Same PHP backend — only the window chrome changes.

Requires: Edge WebView2 Runtime (already on most Win10/11 PCs).
