行動商城（Windows 免安裝版）
================================

使用方式
--------
1. 把整個壓縮檔「解壓縮」到一個資料夾（不要直接在壓縮檔裡執行）。
   建議放在路徑沒有中文與空白的位置，例如 C:\MobileShop
2. 雙擊 start.bat
   - 第一次執行會自動建立資料庫，約 30 秒；Windows 若詢問防火牆，選「允許」或「取消」皆可（只在本機使用）。
   - 完成後會自動開啟瀏覽器 http://localhost:8000
3. 管理員帳號與密碼在第一次啟動後產生的 ADMIN_LOGIN.txt。
   一般使用者可直接在畫面上「註冊」。
4. 結束：回到黑色視窗按 Ctrl+C，或直接關閉視窗。若有殘留程序，執行 stop.bat。

需求
----
- Windows 10 / 11（64 位元）
- Microsoft Visual C++ 2015-2022 Redistributable (x64)；多數電腦已內建，
  若啟動時提示缺少 VCRUNTIME140.dll 或 MSVCP140.dll，請安裝：
  https://aka.ms/vs/17/release/vc_redist.x64.exe

內容
----
app\      網頁版 App（React Native / Expo 匯出）
api\      PHP API
php\      PHP 8.5（官方 Windows 版，未修改）
mariadb\  MariaDB 11.8（官方 Windows 版，未修改）
data\     第一次執行後產生：資料庫檔案與設定（每台電腦各自獨立，搬家前請先關閉程式）

注意
----
- 資料只存在這台電腦的 data\ 資料夾。要備份就複製這個資料夾；要重來就刪除 data\ 與 api\config.local.php、ADMIN_LOGIN.txt。
- 預設只允許本機（127.0.0.1）連線。
- 這是學習專案（行動資料庫應用 Week01-05 + PHP API），未包含結帳、付款等功能。
