# Bật và tắt trang trên Render

Trang đang chạy: [https://web-ban-do-the-thao-3xpb.onrender.com](https://web-ban-do-the-thao-3xpb.onrender.com)

Service trên Render tên **Web-Ban-Do-The-Thao**.

## Tự ngủ

Gói free tự tắt sau một lúc không ai vào. Không cần bấm gì. Lần mở sau đợi khoảng một phút thì trang hiện lại.

## Tắt hẳn

1. Vào [render.com](https://render.com), mở service **Web-Ban-Do-The-Thao**.
2. Vào **Settings**.
3. Bấm **Suspend Web Service**.
4. Hộp thoại bắt gõ đúng dòng sau, rồi bấm nút đỏ:

```text
sudo suspend web service Web-Ban-Do-The-Thao
```

Dòng này chỉ dán vào ô trên trang Render. Không chạy trong PowerShell trên máy.

Sau khi tắt, mở URL sẽ không vào được cho đến khi bật lại.

## Bật lại

1. Mở cùng service **Web-Ban-Do-The-Thao**.
2. Vào **Settings**.
3. Bấm **Resume Web Service**.
4. Đợi trạng thái **Live**, rồi mở URL ở đầu file.
