import numpy as np
import faiss
import cv2
from PIL import Image
from sklearn.cluster import KMeans
import torch
import open_clip
import os

# --- Thiết lập model CLIP ---
device = "cuda" if torch.cuda.is_available() else "cpu"
clip_model, _, clip_preprocess = open_clip.create_model_and_transforms(
    model_name='ViT-B-32',
    pretrained='openai'
)
clip_model = clip_model.to(device)
clip_model.eval()

# --- Đường dẫn file FAISS ---
BASE_DIR = os.path.dirname(__file__)
INDEX_PATH = os.path.join(BASE_DIR, "index.faiss")
PRODUCT_IDS_PATH = os.path.join(BASE_DIR, "product_ids.npy")
IMAGE_PATHS_PATH = os.path.join(BASE_DIR, "image_paths.npy")

# --- Khai báo biến global ---
index = None
product_ids = None
image_paths = None

# --- Hàm load lại index mỗi lần search ---
def load_index():
    global index, product_ids, image_paths

    if not os.path.exists(INDEX_PATH):
        raise Exception("❌ Không tìm thấy file index.faiss")

    index = faiss.read_index(INDEX_PATH)
    product_ids = np.load(PRODUCT_IDS_PATH, allow_pickle=True)
    image_paths = np.load(IMAGE_PATHS_PATH, allow_pickle=True)

# --- Hàm lấy embedding CLIP ---
def get_clip_embedding(image_path):
    image = clip_preprocess(Image.open(image_path)).unsqueeze(0).to(device)
    with torch.no_grad():
        emb = clip_model.encode_image(image)
        emb /= emb.norm(dim=-1, keepdim=True)
    return emb.cpu().numpy()[0]

# --- Hàm lấy màu chủ đạo ---
def get_dominant_color(image_path, k=3):
    img = cv2.imread(image_path)
    if img is None:
        return np.zeros(3)
    img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    img = img.reshape((-1, 3))

    kmeans = KMeans(n_clusters=k, random_state=42).fit(img)
    counts = np.bincount(kmeans.labels_)
    dominant_color = kmeans.cluster_centers_[np.argmax(counts)]
    return dominant_color / 255.0

# --- Hàm tìm kiếm ---
def search_similar_products(query_image_path, top_k=10):
    # ✅ Luôn reload index mới nhất
    load_index()

    # 1. Lấy embedding
    clip_emb = get_clip_embedding(query_image_path)
    color_emb = get_dominant_color(query_image_path) * 10
    query_emb = np.concatenate([clip_emb, color_emb]).astype('float32').reshape(1, -1)

    # 2. Normalize
    faiss.normalize_L2(query_emb)

    # 3. Search
    distances, indices = index.search(query_emb, top_k)

    # 4. Trả kết quả (mỗi SP chỉ lấy 1 ảnh)
    results = []
    seen_products = set()

    for i in indices[0]:
        sp_ma = str(product_ids[i])
        if sp_ma in seen_products:
            continue
        seen_products.add(sp_ma)

        results.append({
            "product_id": sp_ma,
            "image_path": str(image_paths[i])
        })

        if len(results) >= top_k:
            break

    return results
