/*#[cfg_attr(mobile, tauri::mobile_entry_point)]*/
/*pub fn run() {
  tauri::Builder::default()
    .setup(|app| {
      if cfg!(debug_assertions) {
        app.handle().plugin(
          tauri_plugin_log::Builder::default()
            .level(log::LevelFilter::Info)
            .build(),
        )?;
      }
      Ok(())
    })
    .plugin(tauri_plugin_opener::init())
    .run(tauri::generate_context!())
    .expect("error while running tauri application");
}*/



use std::process::{Command, Stdio};
use tauri::Manager;

#[cfg(target_os = "windows")]
use std::os::windows::process::CommandExt;

fn clean_windows_path(path: &std::path::Path) -> std::path::PathBuf {
    let text = path.to_string_lossy();

    if let Some(cleaned) = text.strip_prefix(r"\\?\") {
        std::path::PathBuf::from(cleaned)
    } else {
        path.to_path_buf()
    }
}


#[cfg_attr(mobile, tauri::mobile_entry_point)]

pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            
            
            
            
            if !cfg!(debug_assertions) {
    let resource_dir = app
        .path()
        .resource_dir()
        .expect("Impossible de trouver le dossier resources");

    let php_exe =
    clean_windows_path(&resource_dir.join("php").join("php.exe"));

let engine_dir =
    clean_windows_path(&resource_dir.join("engine"));

let engine_public =
    clean_windows_path(&engine_dir.join("public"));

    let mut command = Command::new(&php_exe);

    command
        .arg("-S")
        .arg("127.0.0.1:8787")
        .arg("-t")
        .arg(&engine_public)
        .current_dir(&engine_dir)
        .stdin(Stdio::null())
        .stdout(Stdio::null())
        .stderr(Stdio::null());

    #[cfg(target_os = "windows")]
    command.creation_flags(0x08000000);

    command
        .spawn()
        .expect("Impossible de démarrer HESTIA Engine");
}

            Ok(())
        })
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_dialog::init())
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}