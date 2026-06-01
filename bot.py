import requests
import time

# Konfigurasi Token dan API Target
TOKEN = "7323628077:AAH0xnx4WDR9xzaXxPFTygL_kT5VBwTvNHw"
MODORA_API = "http://127.0.0.1/index.php?ajax=1" 

def check_bot():
    url = f"https://api.telegram.org/bot{TOKEN}/getMe"
    try:
        r = requests.get(url).json()
        if r.get("ok"):
            print(f"[+] Login sukses ke bot: @{r['result']['username']}")
            return True
        else:
            print("[-] FATAL ERROR: Token salah atau ditolak Telegram!", r)
            return False
    except Exception as e:
        print("[-] FATAL ERROR: VPS tidak bisa konek ke Telegram!", e)
        return False

def get_updates(offset=None):
    url = f"https://api.telegram.org/bot{TOKEN}/getUpdates"
    params = {"timeout": 30, "offset": offset}
    try:
        r = requests.get(url, params=params)
        return r.json()
    except:
        return None

def send_message(chat_id, text, disable_preview=True):
    url = f"https://api.telegram.org/bot{TOKEN}/sendMessage"
    payload = {
        "chat_id": chat_id, 
        "text": text, 
        "parse_mode": "HTML",
        "disable_web_page_preview": disable_preview
    }
    requests.post(url, json=payload)

def main():
    if not check_bot():
        return
        
    print("[+] Sistem Bot Modora Aktif dan Menunggu Pesan...")
    offset = None
    
    while True:
        updates = get_updates(offset)
        if updates and "result" in updates:
            for item in updates["result"]:
                offset = item["update_id"] + 1
                message = item.get("message", {})
                chat_id = message.get("chat", {}).get("id")
                text = message.get("text", "")

                if text.startswith("http"):
                    print(f"[+] Ada link masuk. Memulai Mode Turbo...")
                    send_message(chat_id, "[MENYAMBUNGKAN]\nMemproses link target... Mode Turbo Aria2 aktif di background.")
                    try:
                        payload = {
                            "url_download": text, 
                            "custom_name": "File_Telegram", 
                            "task_id": int(time.time())
                        }
                        res = requests.post(MODORA_API, data=payload, timeout=600)
                        data = res.json()
                        
                        if data.get("status") == "success":
                            msg = f"[SUKSES]\n\nNama File: {data.get('filename')}\nUkuran: {data.get('filesize')}\n\nLink Akses:\n{data.get('link')}"
                        else:
                            msg = f"[GAGAL]\nPesan Error: {data.get('message')}"
                            
                        send_message(chat_id, msg)
                        print("[+] File selesai di-fetch dan link dikirim ke user.")
                    except Exception as e:
                        print("[-] ERROR FETCHING FILE:", e)
                        send_message(chat_id, f"[SISTEM ERROR]\nDetail: Terjadi kesalahan saat menarik file di PHP.")
                        
                elif text == "/start":
                    print(f"[+] User {chat_id} menekan /start")
                    send_message(chat_id, "[MODORA CLOUD BOT]\nKirimkan URL valid (Direct/MediaFire) untuk di-fetch ke server.")
                    
        time.sleep(1)

if __name__ == '__main__':
    # Memastikan tidak ada webhook yang nyangkut
    requests.get(f"https://api.telegram.org/bot{TOKEN}/deleteWebhook")
    main()
