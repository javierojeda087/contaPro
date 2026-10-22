package com.contapro.sifen.controller;

import com.contapro.sifen.dto.EmitirDeRequest;
import com.contapro.sifen.dto.EmitirDeResponse;
import com.contapro.sifen.dto.EventoSifenRequest;
import com.contapro.sifen.service.SifenService;
import com.contapro.sifen.util.CdcUtil;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.time.LocalDateTime;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Controlador REST del Microservicio SIFEN API para ContaPro.
 */
@RestController
@RequestMapping("/api/v1/sifen")
@CrossOrigin(origins = "*")
public class SifenController {

    private final SifenService sifenService;

    public SifenController(SifenService sifenService) {
        this.sifenService = sifenService;
    }

    /**
     * Endpoint de diagnóstico y salud del microservicio en Oracle Cloud (OCI).
     */
    @GetMapping("/health")
    public ResponseEntity<Map<String, Object>> health() {
        Map<String, Object> status = new LinkedHashMap<>();
        status.put("status", "UP");
        status.put("service", "ContaPro SIFEN API (Java Spring Boot)");
        status.put("sifenVersion", "150");
        status.put("timestamp", LocalDateTime.now().toString());
        return ResponseEntity.ok(status);
    }

    /**
     * Emite un Documento Electrónico (Factura, Nota de Crédito, Nota de Débito, Autofactura):
     * - Calcula el CDC de 44 dígitos (Módulo 11)
     * - Construye el XML oficial v150
     * - Firma digitalmente con el certificado .p12 de la empresa
     * - Genera la URL del QR oficial con hash SHA-256 + CSC
     * - Envía a SIFEN (Homologación o Producción)
     */
    @PostMapping("/emitir")
    public ResponseEntity<EmitirDeResponse> emitir(@RequestBody EmitirDeRequest request) {
        EmitirDeResponse response = sifenService.emitirDocumento(request);
        return ResponseEntity.ok(response);
    }

    /**
     * Envía un Evento de Cancelación de Documento Electrónico (código de evento 1) a SIFEN.
     */
    @PostMapping("/evento/cancelar")
    public ResponseEntity<Map<String, Object>> cancelar(@RequestBody EventoSifenRequest request) {
        return ResponseEntity.ok(sifenService.procesarEvento(request, 1));
    }

    /**
     * Envía un Evento de Inutilización de Rango de Numeración (código de evento 2) a SIFEN.
     */
    @PostMapping("/evento/inutilizar")
    public ResponseEntity<Map<String, Object>> inutilizar(@RequestBody EventoSifenRequest request) {
        return ResponseEntity.ok(sifenService.procesarEvento(request, 2));
    }

    /**
     * Decodifica y valida instantáneamente cualquier CDC de 44 dígitos (extrae RUC, Factura, Fecha, Tipo Doc).
     */
    @GetMapping("/decodificar-cdc/{cdc}")
    public ResponseEntity<Map<String, Object>> decodificarCdc(@PathVariable("cdc") String cdc) {
        return ResponseEntity.ok(CdcUtil.decodificarCdc(cdc));
    }

    /**
     * Consulta un CDC en SIFEN (decodifica estructura Módulo 11 y consulta SOAP si se provee certificado).
     */
    @PostMapping("/consultar-cdc")
    public ResponseEntity<Map<String, Object>> consultarCdcPost(@RequestBody Map<String, String> body) {
        String cdc = body.getOrDefault("cdc", "");
        String ambiente = body.getOrDefault("ambiente", "TEST");
        String certBase64 = body.get("certificadoBase64");
        String certPath = body.get("certificadoPath");
        String certPassword = body.get("certificadoPassword");
        return ResponseEntity.ok(sifenService.consultarCdc(cdc, ambiente, certBase64, certPath, certPassword));
    }
}
