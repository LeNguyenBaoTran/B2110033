from flask import Flask, request, jsonify
from flask_cors import CORS
import os
import subprocess
from search_image import search_similar_products

app = Flask(__name__)
CORS(app)

# Thư mục lưu ảnh upload tạm thời
UPLOAD_FOLDER = os.path.join(os.path.dirname(__file__), "uploads")
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# --- API tìm kiếm bằng ảnh ---
@app.route("/search", methods=["POST"])
def search():
    if "file" not in request.files:
        return jsonify({"error": "Không có file tải lên!"}), 400

    file = request.files["file"]
    if file.filename == "":
        return jsonify({"error": "Tên file không hợp lệ!"}), 400

    # Lưu ảnh tạm
    file_path = os.path.join(UPLOAD_FOLDER, file.filename)
    file.save(file_path)
    print("[DEBUG] Flask nhận file:", file_path)

    # Tìm kiếm ảnh tương tự
    results = search_similar_products(file_path)
    print("[DEBUG] Kết quả tìm kiếm:", results)

    return jsonify({"results": results})


# --- API reload FAISS index (khi admin bấm nút cập nhật) ---
@app.route("/reload_index", methods=["POST"])
def reload_index():
    try:
        subprocess.run(["python", "create_index.py"], check=True)
        return jsonify({
            "status": "success",
            "message": "✅ Đã cập nhật index tìm kiếm ảnh!"
        })
    except Exception as e:
        return jsonify({
            "status": "error",
            "message": str(e)
        }), 500


# --- Chạy Flask ---
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
