import os
import sys
from pathlib import Path


APP_DIR = Path(__file__).resolve().parent
if str(APP_DIR) not in sys.path:
    sys.path.insert(0, str(APP_DIR))

os.environ.setdefault("FCG_COOKIE_PATH", "/portal")

from app import application  # noqa: E402
