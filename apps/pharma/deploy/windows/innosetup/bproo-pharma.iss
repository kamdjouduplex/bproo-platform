; Inno Setup 6 — Bproo Pharma Desktop
; Build payload first:  .\build-release.ps1 -Version 0.1.0
; Then compile:         .\compile-installer.ps1 -Version 0.1.0
;
; Source tree: dist\payload\*

#define MyAppName "Bproo Pharma Desktop"
#ifndef MyAppVersion
  #define MyAppVersion "0.1.0"
#endif
#define MyAppPublisher "Afroinov"
#define MyAppURL "https://afroinov.com"
#define MyAppId "{{8F3C2A1B-9D4E-4F70-A6B1-2C9E7D5F4A10}"

[Setup]
AppId={#MyAppId}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
DefaultDirName={autopf}\Bproo\PharmaDesktop
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
LicenseFile=
OutputDir=..\output
OutputBaseFilename=bproo-pharma-desktop-{#MyAppVersion}-setup
SetupIconFile=
Compression=lzma2/ultra
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayName={#MyAppName}
VersionInfoVersion={#MyAppVersion}.0
VersionInfoCompany={#MyAppPublisher}
CloseApplications=no

[Languages]
Name: "french"; MessagesFile: "compiler:Languages\French.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Dirs]
; Laravel must write logs/cache/sessions/sqlite under Program Files
Name: "{app}\storage"; Permissions: users-modify
Name: "{app}\storage\logs"; Permissions: users-modify
Name: "{app}\storage\framework"; Permissions: users-modify
Name: "{app}\storage\framework\cache"; Permissions: users-modify
Name: "{app}\storage\framework\sessions"; Permissions: users-modify
Name: "{app}\storage\framework\views"; Permissions: users-modify
Name: "{app}\storage\app"; Permissions: users-modify
Name: "{app}\bootstrap\cache"; Permissions: users-modify
Name: "{app}\database"; Permissions: users-modify

[Files]
; Full payload produced by build-release.ps1
Source: "..\dist\payload\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\BprooPharma.vbs"; WorkingDir: "{app}"
Name: "{group}\{#MyAppName} (console)"; Filename: "{app}\Start-BprooPharma.ps1"; WorkingDir: "{app}"
Name: "{group}\Activer la licence"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\Activate-Licence.ps1"""; WorkingDir: "{app}"
Name: "{group}\Configurer URL Control Center"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\Set-ControlCenterUrl.ps1"""; WorkingDir: "{app}"
Name: "{group}\Configuration initiale"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\First-Run-Setup.ps1"""; WorkingDir: "{app}"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\BprooPharma.vbs"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\First-Run-Setup.ps1"""; StatusMsg: "Configuration initiale (SQLite, migrations)..."; Flags: waituntilterminated
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\Activate-Licence.ps1"""; Description: "Activer la licence maintenant"; Flags: postinstall skipifsilent unchecked waituntilterminated
Filename: "{app}\BprooPharma.vbs"; Description: "Lancer Bproo Pharma Desktop"; Flags: nowait postinstall skipifsilent unchecked

[UninstallDelete]
Type: filesandordirs; Name: "{app}\storage\framework\*"
Type: filesandordirs; Name: "{app}\storage\logs\*"
Type: filesandordirs; Name: "{app}\bootstrap\cache\*"
