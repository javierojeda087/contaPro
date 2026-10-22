package com.contapro.sifen.config;

import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;

/**
 * Filtro de seguridad que valida la cabecera X-API-Key en las peticiones desde ContaPro (PHP cPanel)
 * hacia este microservicio en Oracle Cloud Infrastructure (OCI).
 */
@Component
public class ApiKeyFilter extends OncePerRequestFilter {

    @Value("${sifen.api.key:contapro-sifen-secret-2026}")
    private String configuredApiKey;

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {

        // Permitir CORS preflight y endpoint público de salud (/api/v1/sifen/health)
        response.setHeader("Access-Control-Allow-Origin", "*");
        response.setHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
        response.setHeader("Access-Control-Allow-Headers", "Content-Type, X-API-Key, Authorization");

        if ("OPTIONS".equalsIgnoreCase(request.getMethod())) {
            response.setStatus(HttpServletResponse.SC_OK);
            return;
        }

        String path = request.getRequestURI();
        if (path.endsWith("/health") || path.contains("/decodificar-cdc/")) {
            filterChain.doFilter(request, response);
            return;
        }

        String requestKey = request.getHeader("X-API-Key");
        if (requestKey == null || requestKey.isBlank()) {
            requestKey = request.getParameter("apiKey");
        }

        if (configuredApiKey != null && !configuredApiKey.isBlank() && !configuredApiKey.equals(requestKey)) {
            response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
            response.setContentType("application/json;charset=UTF-8");
            response.getWriter().write("{\"status\":\"error\",\"message\":\"X-API-Key inválida o no proporcionada.\"}");
            return;
        }

        filterChain.doFilter(request, response);
    }
}
