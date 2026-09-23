FROM python:3.12-slim-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg tesseract-ocr tesseract-ocr-por tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd -g 33 -o app && useradd -m -u 33 -g 33 -o app
COPY worker/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt
COPY worker /worker
RUN mkdir -p /data /home/app/.cache && chown -R 33:33 /data /home/app
WORKDIR /worker
USER app
CMD ["python", "/worker/service.py"]
