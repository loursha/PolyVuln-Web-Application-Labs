# ============================================================================
# Dockerfile -- Wick & Wax Co. (intentionally vulnerable training app)
#
# NOTE: This image is built for an ISOLATED LAB NETWORK ONLY. It runs Flask's
# debug server with debug=True by design (see VULNERABILITY #6). Never
# publish this image to a public registry or expose it to the internet.
# ============================================================================
FROM python:3.11-slim

WORKDIR /app

# System tool used by the intentionally-vulnerable /admin/ping-supplier
# command-injection demo (VULNERABILITY #12).
RUN apt-get update && apt-get install -y --no-install-recommends iputils-ping \
    && rm -rf /var/lib/apt/lists/*

COPY requirements-run.txt .
RUN pip install --no-cache-dir -r requirements-run.txt

COPY . .

RUN python3 init_db.py

EXPOSE 5000

CMD ["python3", "app.py"]
