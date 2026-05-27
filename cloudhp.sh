#!/bin/bash
clear
echo "============================================="
echo "   Auto Install Cloud HP (Ubuntu 20)         "
echo "============================================="
echo "Memproses instalasi dependencies dan Docker..."

# Update dan install paket dasar
sudo apt-get update -y
sudo apt-get install -y curl wget git qemu-kvm libvirt-daemon-system libvirt-clients bridge-utils

# Cek apakah Docker sudah terinstall
if ! command -v docker &> /dev/null; then
    echo "Docker belum ada, menginstal Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
    sudo systemctl start docker
    sudo systemctl enable docker
else
    echo "Docker sudah terinstall, lanjut!"
fi

# Hapus container lama jika ada biar gak bentrok
sudo docker rm -f cloud-hp &> /dev/null

echo "Mendownload dan Menjalankan Android Cloud HP..."
# Menjalankan emulator Android v11.0
# Port 6080 untuk Web UI, 5555 untuk akses ADB
sudo docker run -d \
  -p 6080:6080 \
  -p 5555:5555 \
  -e DEVICE="Samsung Galaxy S10" \
  -e WEB_VNC=true \
  --name cloud-hp \
  --privileged \
  budtmo/docker-android:emulator_11.0

echo "============================================="
echo " INSTALASI SELESAI!                          "
echo "============================================="
echo "Cloud HP lu lagi booting (biasanya butuh 2-5 menit)."
echo ""
echo "Akses lewat browser di:"
echo "👉 http://203.175.11.199:6080"
echo ""
echo "Cara Install APK:"
echo "1. Lewat Web: Buka Chrome dari dalam Cloud HP, terus download & install APK kayak biasa."
echo "2. Lewat ADB (Remote): ketik 'adb connect 203.175.11.199:5555' di terminal PC lu, lalu 'adb install aplikasi.apk'"
echo "============================================="
