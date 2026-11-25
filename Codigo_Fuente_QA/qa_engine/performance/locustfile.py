import os
from gevent import spawn_later
from gevent.lock import Semaphore
from locust import HttpUser, task, between

MAX_REQUESTS = int(os.getenv("QA_LOCUST_MAX_REQUESTS", "20"))
BASE_URL = os.getenv("QA_LOCUST_BASE_URL", "http://127.0.0.1:8000")
_REQUEST_LOCK = Semaphore()
_REQUESTS_RESERVED = 0
_SHUTDOWN_SCHEDULED = False


class QaReadUser(HttpUser):
    wait_time = between(0.2, 0.8)
    host = BASE_URL

    def request_limit_reached(self):
        global _REQUESTS_RESERVED
        with _REQUEST_LOCK:
            if _REQUESTS_RESERVED < MAX_REQUESTS:
                _REQUESTS_RESERVED += 1
                return False
        return True

    def issue_limited_request(self, path):
        global _SHUTDOWN_SCHEDULED
        if not self.request_limit_reached():
            self.client.get(path, timeout=3)
        if self.environment.runner.stats.total.num_requests >= MAX_REQUESTS:
            with _REQUEST_LOCK:
                if not _SHUTDOWN_SCHEDULED:
                    _SHUTDOWN_SCHEDULED = True
                    spawn_later(1.1, self.environment.runner.quit)

    @task(5)
    def login_page(self):
        self.issue_limited_request("/login")

    @task(2)
    def health(self):
        self.issue_limited_request("/up")
