; Inno Setup skeleton — Bproo Pharma Desktop (Phase 4)
; Compile later with Inno Setup 6+. For now prefer install.ps1.
; Does not replace SaaS Docker packaging.

#define MyAppName "Bproo Pharma Desktop"
#define MyAppVersion "0.1.0"
#define MyAppPublisher "Afroinov"
#define MyAppURL "https://afroinov.com"
#define MyAppExeName "start.ps1"

[Setup]
AppId={{BPROO-PHARMA-DESKTOP-001}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={autopf}\Bproo\PharmaDesktop
DefaultGroupName={#MyAppName}
OutputDir=..\output
OutputBaseFilename=bproo-pharma-desktop-setup
Compression=lzma
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin

[Languages]
Name: "french"; MessagesFile: "compiler:Languages\French.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
; Point Source to a release folder produced by CI (app + vendor + php runtime bundle).
; Source: "..\..\..\*\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\deploy\windows\start.ps1"""
Name: "{commondesktop}\{#MyAppName}"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\deploy\windows\start.ps1"""

[Run]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\deploy\windows\install.ps1"""; StatusMsg: "Configuration initiale…"; Flags: waituntilterminated
