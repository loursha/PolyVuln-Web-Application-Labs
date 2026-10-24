FROM python:3.11-slim

# ping utility for the (intentionally vulnerable) diagnostics feature
RUN apt-get update && apt-get install -y --no-install-recommends iputils-ping \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

RUN python init_db.py

EXPOSE 5000
# CWE-319: plain HTTP, debug on
CMD ["python", "app.py"]
