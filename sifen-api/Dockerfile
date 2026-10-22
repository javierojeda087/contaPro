# ============================================================================
# Dockerfile Multi-Stage para ContaPro SIFEN API en Oracle Cloud (OCI)
# Compatible con instancias ARM64 (Ampere A1) y AMD64 (x86_64)
# ============================================================================
FROM maven:3.9.6-eclipse-temurin-21 AS build
WORKDIR /app
COPY pom.xml .
RUN mvn dependency:go-offline -B
COPY src ./src
RUN mvn clean package -DskipTests -B

FROM eclipse-temurin:21-jre
WORKDIR /app
ENV TZ=America/Asuncion
ENV PORT=8085
EXPOSE 8085
COPY --from=build /app/target/sifen-api.jar /app/sifen-api.jar
ENTRYPOINT ["java", "-Xms128m", "-Xmx512m", "-jar", "/app/sifen-api.jar"]
