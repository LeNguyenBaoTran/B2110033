import os
import numpy as np
import faiss
from PIL import Image
import torch
import open_clip
import cv2
from sklearn.cluster import KMeans
import pymysql

# --- Thư mục gốc dự án ---
ROOT_DIR = r"C:\xampp\htdocs\LV_QuanLy_BanTrangPhuc\LV_QuanLy_BanTrangPhuc"

# --- File lưu index ---
INDEX_PATH = "index.faiss"
PRODUCT_IDS_PATH = "product_ids.npy"
IMAGE_PATHS_PATH = "image_paths.npy"

# --- Thiết lập model CLIP ---
device = "cuda" if torch.cuda.is_available() else "cpu"
clip_model, _, clip_preprocess = open_clip.create_model_and_transforms(
    model_name='ViT-B-32',
    pretrained='openai'
)
clip_model = clip_model.to(device)
clip_model.eval()

# --- Hàm lấy embedding CLIP ---
def get_clip_embedding(image_path):
    img = clip_preprocess(Image.open(image_path)).unsqueeze(0).to(device)
    with torch.no_grad():
        emb = clip_model.encode_image(img)
    emb /= emb.norm(dim=-1, keepdim=True)
    return emb.cpu().numpy()[0]

# --- Hàm lấy màu chủ đạo ---
def get_dominant_color(image_path, k=3):
    img = cv2.imread(image_path)
    img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    img = img.reshape((-1, 3))
    kmeans = KMeans(n_clusters=k, random_state=42).fit(img)
    counts = np.bincount(kmeans.labels_)
    dominant_color = kmeans.cluster_centers_[np.argmax(counts)]
    return dominant_color / 255.0

# --- Kết nối DB ---
conn = pymysql.connect(
    host="localhost",
    user="root",
    password="",
    db="ql_ban_trang_phuc",
    charset="utf8"
)
cur = conn.cursor()

# --- Lấy tất cả ảnh từ DB ---
cur.execute("SELECT ANH_DUONGDAN, SP_MA FROM anh_san_pham ORDER BY ANH_MA ASC")
rows = cur.fetchall()

embeddings = []
product_ids = []
image_paths = []

for anh_duongdan, sp_ma in rows:

    # --- Tạo đường dẫn tuyệt đối để load ảnh ---
    relative_path = anh_duongdan.replace("../", "").replace("/", os.sep)
    abs_path = os.path.join(ROOT_DIR, relative_path)

    if not os.path.exists(abs_path):
        print(f"⚠️ Ảnh không tồn tại: {abs_path}")
        continue

    try:
        clip_emb = get_clip_embedding(abs_path)
        color_emb = get_dominant_color(abs_path) * 10
        full_emb = np.concatenate([clip_emb, color_emb]).astype('float32')

        embeddings.append(full_emb)
        product_ids.append(sp_ma)

        # --- LƯU lại đường dẫn tương đối (chuẩn nhất) ---
        image_paths.append(anh_duongdan)

    except Exception as e:
        print(f"❌ Lỗi xử lý ảnh {abs_path}: {e}")

if len(embeddings) == 0:
    raise ValueError("Không có ảnh hợp lệ từ DB")

# --- Convert mảng ---
embeddings = np.array(embeddings).astype('float32')
faiss.normalize_L2(embeddings)

# --- Tạo index FAISS ---
dim = embeddings.shape[1]
index = faiss.IndexFlatL2(dim)
index.add(embeddings)

# --- Lưu index và mapping ---
faiss.write_index(index, INDEX_PATH)
np.save(PRODUCT_IDS_PATH, np.array(product_ids))
np.save(IMAGE_PATHS_PATH, np.array(image_paths))

print(f"✅ Tạo index xong với {len(product_ids)} ảnh!")
print(f"📁 Saved: {INDEX_PATH}, {PRODUCT_IDS_PATH}, {IMAGE_PATHS_PATH}")
