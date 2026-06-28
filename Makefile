compose_up_d:
	docker compose -f docker-compose.yml up -d
compose_up_build:
	docker compose -f docker-compose.yml up --build 
compose_up_build_d:
	dnocker compose -f docker-compose.yml up --build -d
compose_down:
	docker compose -f docker-compose.yml down
compose_clean:
	docker compose -f docker-compose.yml down
	docker compose -f docker-compose.yml rm
restart:
	docker compose -f docker-compose.yml down && docker compose -f docker-compose.yml up --build -d