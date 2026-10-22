package com.contapro.sifen.dto;

import java.math.BigDecimal;

public class EmitirDeResponse {
    private String status;           // "success" o "error"
    private String cdc;              // 44 dígitos
    private String cdcFormateado;    // Grupos de 4 dígitos para el KuDE
    private String qrUrl;            // Enlace QR oficial de e-Kuatia con cHashQR
    private String estadoSifen;      // "APROBADO", "RECHAZADO", "FIRMADO_PENDIENTE", "SIMULADO"
    private String codigoRespuesta;  // Ej: "0260" (Aprobado)
    private String mensajeRespuesta;
    private String nroProtocolo;     // dProtAut devuelto por SIFEN
    private String fechaProceso;
    private String nroFactura;       // 001-001-0000001
    private String xmlFirmado;
    private BigDecimal grav10;
    private BigDecimal iva10;
    private BigDecimal grav5;
    private BigDecimal iva5;
    private BigDecimal exentas;
    private BigDecimal totalIva;
    private BigDecimal totalGeneral;

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getCdc() { return cdc; }
    public void setCdc(String cdc) { this.cdc = cdc; }

    public String getCdcFormateado() { return cdcFormateado; }
    public void setCdcFormateado(String cdcFormateado) { this.cdcFormateado = cdcFormateado; }

    public String getQrUrl() { return qrUrl; }
    public void setQrUrl(String qrUrl) { this.qrUrl = qrUrl; }

    public String getEstadoSifen() { return estadoSifen; }
    public void setEstadoSifen(String estadoSifen) { this.estadoSifen = estadoSifen; }

    public String getCodigoRespuesta() { return codigoRespuesta; }
    public void setCodigoRespuesta(String codigoRespuesta) { this.codigoRespuesta = codigoRespuesta; }

    public String getMensajeRespuesta() { return mensajeRespuesta; }
    public void setMensajeRespuesta(String mensajeRespuesta) { this.mensajeRespuesta = mensajeRespuesta; }

    public String getNroProtocolo() { return nroProtocolo; }
    public void setNroProtocolo(String nroProtocolo) { this.nroProtocolo = nroProtocolo; }

    public String getFechaProceso() { return fechaProceso; }
    public void setFechaProceso(String fechaProceso) { this.fechaProceso = fechaProceso; }

    public String getNroFactura() { return nroFactura; }
    public void setNroFactura(String nroFactura) { this.nroFactura = nroFactura; }

    public String getXmlFirmado() { return xmlFirmado; }
    public void setXmlFirmado(String xmlFirmado) { this.xmlFirmado = xmlFirmado; }

    public BigDecimal getGrav10() { return grav10; }
    public void setGrav10(BigDecimal grav10) { this.grav10 = grav10; }

    public BigDecimal getIva10() { return iva10; }
    public void setIva10(BigDecimal iva10) { this.iva10 = iva10; }

    public BigDecimal getGrav5() { return grav5; }
    public void setGrav5(BigDecimal grav5) { this.grav5 = grav5; }

    public BigDecimal getIva5() { return iva5; }
    public void setIva5(BigDecimal iva5) { this.iva5 = iva5; }

    public BigDecimal getExentas() { return exentas; }
    public void setExentas(BigDecimal exentas) { this.exentas = exentas; }

    public BigDecimal getTotalIva() { return totalIva; }
    public void setTotalIva(BigDecimal totalIva) { this.totalIva = totalIva; }

    public BigDecimal getTotalGeneral() { return totalGeneral; }
    public void setTotalGeneral(BigDecimal totalGeneral) { this.totalGeneral = totalGeneral; }
}
