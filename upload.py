"""Saytni FTP orqali yuklash. Foydalanish: python upload.py
Parol so'raladi (faylga yozilmaydi)."""
import ftplib, getpass, os

HOST = "176.9.111.172"
USER = "6af21c3a2ca58_persi"
REMOTE = "/www/ai.6af21c3a2cb17.clouduz.ru"
LOCAL = os.path.join(os.path.dirname(os.path.abspath(__file__)), "site")

ftp = ftplib.FTP(HOST, timeout=30)
ftp.login(USER, getpass.getpass("FTP parol: "))
ftp.cwd(REMOTE)
print("Serverdagi fayllar:", ftp.nlst())

for root, dirs, files in os.walk(LOCAL):
    rel = os.path.relpath(root, LOCAL)
    target = REMOTE if rel == "." else f"{REMOTE}/{rel.replace(os.sep, '/')}"
    try:
        ftp.mkd(target)
    except ftplib.error_perm:
        pass  # papka bor
    for f in files:
        with open(os.path.join(root, f), "rb") as fh:
            ftp.storbinary(f"STOR {target}/{f}", fh)
        print("Yuklandi:", f"{target}/{f}")
ftp.quit()
print("Tayyor: https://ai.6af21c3a2cb17.clouduz.ru/")
