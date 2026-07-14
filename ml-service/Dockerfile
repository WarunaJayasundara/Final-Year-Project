# Deployed as its own separate free web service. Called server-to-server
# from the Laravel backend (never directly from the browser), so it has no
# cookie/CORS concerns - only needs its URL set as ML_SERVICE_URL on the
# backend service.
FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

ENV PORT=8100
EXPOSE 8100

CMD ["sh", "-c", "uvicorn app:app --host 0.0.0.0 --port ${PORT}"]
