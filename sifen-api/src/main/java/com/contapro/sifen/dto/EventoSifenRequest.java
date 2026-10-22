package com.contapro.sifen.dto;

public class EventoSifenRequest {
    private String ambiente = "TEST";
    private String certificadoBase64;
    private String certificadoPath;
    private String certificadoPassword;

    // Para Cancelación (Evento 1)
    private String cdc;
    private String motivo = "Cancelación de comprobante electrónico por error en datos de emisión";

    // Para Inutilización de numeración (Evento 2)
    private String rucEmisor;
    private String timbrado;
    private String establecimiento = "001";
    private String puntoExpedicion = "001";
    private String numeroInicio;
    private String numeroFin;
    private Integer tipoDocumento = 1;

    public String getAmbiente() { return ambiente; }
    public void setAmbiente(String ambiente) { this.ambiente = ambiente; }

    public String getCertificadoBase64() { return certificadoBase64; }
    public void setCertificadoBase64(String certificadoBase64) { this.certificadoBase64 = certificadoBase64; }

    public String getCertificadoPath() { return certificadoPath; }
    public void setCertificadoPath(String certificadoPath) { this.certificadoPath = certificadoPath; }

    public String getCertificadoPassword() { return certificadoPassword; }
    public void setCertificadoPassword(String certificadoPassword) { this.certificadoPassword = certificadoPassword; }

    public String getCdc() { return cdc; }
    public void setCdc(String cdc) { this.cdc = cdc; }

    public String getMotivo() { return motivo; }
    public void setMotivo(String motivo) { this.motivo = motivo; }

    public String getRucEmisor() { return rucEmisor; }
    public void setRucEmisor(String rucEmisor) { this.rucEmisor = rucEmisor; }

    public String getTimbrado() { return timbrado; }
    public void setTimbrado(String timbrado) { this.timbrado = timbrado; }

    public String getEstablecimiento() { return establecimiento; }
    public void setEstablecimiento(String establecimiento) { this.establecimiento = establecimiento; }

    public String getPuntoExpedicion() { return puntoExpedicion; }
    public void setPuntoExpedicion(String puntoExpedicion) { this.puntoExpedicion = puntoExpedicion; }

    public String getNumeroInicio() { return numeroInicio; }
    public void setNumeroInicio(String numeroInicio) { this.numeroInicio = numeroInicio; }

    public String getNumeroFin() { return numeroFin; }
    public void setNumeroFin(String numeroFin) { this.numeroFin = numeroFin; }

    public Integer getTipoDocumento() { return tipoDocumento; }
    public void setTipoDocumento(Integer tipoDocumento) { this.tipoDocumento = tipoDocumento; }
}
