package com.contapro.sifen.dto;

import java.math.BigDecimal;
import java.util.List;

/**
 * Petición JSON enviada desde ContaPro (PHP) para emitir y autorizar un Documento Electrónico (DE) en SIFEN.
 * Soporta multi-empresa enviando los datos del certificado .p12 (Base64 o ruta en servidor OCI) y CSC por empresa.
 */
public class EmitirDeRequest {
    // 1. Ambiente y credenciales SIFEN de la empresa emisora
    private String ambiente = "TEST"; // "TEST" (Homologación) o "PROD" (Producción)
    private String certificadoBase64; // Archivo .p12/.pfx codificado en Base64
    private String certificadoPath;   // Ruta alternativa al .p12 en el servidor OCI
    private String certificadoPassword;
    private String idCsc = "0001";    // ID del Código de Seguridad del Contribuyente (ej: 0001)
    private String csc;               // Código de Seguridad del Contribuyente de 32 caracteres

    // 2. Datos del Contribuyente Emisor
    private String rucEmisor;         // Ej: "80012345-6" o "5567097-0"
    private Integer tipoContribuyente = 1; // 1 = Persona Física, 2 = Persona Jurídica
    private String razonSocialEmisor;
    private String nombreFantasia;
    private String actividadEconomicaCodigo = "69200";
    private String actividadEconomicaDescripcion = "ACTIVIDADES DE CONTABILIDAD, TENEDURÍA DE LIBROS Y AUDITORÍA";
    private String timbrado;          // 8 dígitos
    private String fechaInicioTimbrado = "2025-01-01";
    private String establecimiento = "001";
    private String puntoExpedicion = "001";
    private String numeroDocumento = "0000001";
    private String serie;
    private String direccionEmisor = "ASUNCION";
    private String numeroCasaEmisor = "0";
    private Integer codigoDepartamentoEmisor = 1;
    private String descripcionDepartamentoEmisor = "CAPITAL";
    private Integer codigoDistritoEmisor = 1;
    private String descripcionDistritoEmisor = "ASUNCION";
    private Integer codigoCiudadEmisor = 1;
    private String descripcionCiudadEmisor = "ASUNCION (DISTRITO)";
    private String telefonoEmisor = "021000000";
    private String emailEmisor = "facturacion@contapro.com.py";

    // 3. Datos de la Operación y Documento Electrónico
    private Integer tipoDocumento = 1; // 1=Factura Electrónica, 4=Autofactura, 5=Nota de Crédito, 6=Nota de Débito
    private String fechaEmision;       // ISO "YYYY-MM-DD" o "YYYY-MM-DDTHH:mm:ss"
    private Integer tipoEmision = 1;   // 1=Normal, 2=Contingencia
    private Integer tipoTransaccion = 1; // 1=Venta de mercadería, 2=Prestación de servicios, 3=Mixto
    private Integer tipoImpuesto = 1;  // 1=IVA, 2=ISC, 4=Ninguno, 5=IVA-Renta
    private String moneda = "PYG";     // PYG, USD, BRL
    private BigDecimal tasaCambio = BigDecimal.ONE;
    private String condicion = "CON";  // "CON" (Contado) o "CRE" (Crédito)
    private String plazoCredito = "30 días";
    private String cdcDocumentoAsociado; // Requerido cuando tipoDocumento es 5 (NCE) o 6 (NDE)
    private String motivoEmisionNota = "Ajuste de precio / Devolución";

    // 4. Datos del Cliente / Receptor
    private String rucCliente;         // Ej: "80079378-1" o cédula
    private String razonSocialCliente;
    private String direccionCliente;
    private String telefonoCliente;
    private String emailCliente;

    // 5. Montos globales (se utilizan si no se envía detalle de ítems o para validación)
    private BigDecimal grav10 = BigDecimal.ZERO;
    private BigDecimal iva10 = BigDecimal.ZERO;
    private BigDecimal grav5 = BigDecimal.ZERO;
    private BigDecimal iva5 = BigDecimal.ZERO;
    private BigDecimal exentas = BigDecimal.ZERO;
    private String observacion;

    // 6. Detalle de ítems del comprobante
    private List<ItemDeDto> items;

    // Getters & Setters
    public String getAmbiente() { return ambiente; }
    public void setAmbiente(String ambiente) { this.ambiente = ambiente; }

    public String getCertificadoBase64() { return certificadoBase64; }
    public void setCertificadoBase64(String certificadoBase64) { this.certificadoBase64 = certificadoBase64; }

    public String getCertificadoPath() { return certificadoPath; }
    public void setCertificadoPath(String certificadoPath) { this.certificadoPath = certificadoPath; }

    public String getCertificadoPassword() { return certificadoPassword; }
    public void setCertificadoPassword(String certificadoPassword) { this.certificadoPassword = certificadoPassword; }

    public String getIdCsc() { return idCsc; }
    public void setIdCsc(String idCsc) { this.idCsc = idCsc; }

    public String getCsc() { return csc; }
    public void setCsc(String csc) { this.csc = csc; }

    public String getRucEmisor() { return rucEmisor; }
    public void setRucEmisor(String rucEmisor) { this.rucEmisor = rucEmisor; }

    public Integer getTipoContribuyente() { return tipoContribuyente; }
    public void setTipoContribuyente(Integer tipoContribuyente) { this.tipoContribuyente = tipoContribuyente; }

    public String getRazonSocialEmisor() { return razonSocialEmisor; }
    public void setRazonSocialEmisor(String razonSocialEmisor) { this.razonSocialEmisor = razonSocialEmisor; }

    public String getNombreFantasia() { return nombreFantasia; }
    public void setNombreFantasia(String nombreFantasia) { this.nombreFantasia = nombreFantasia; }

    public String getActividadEconomicaCodigo() { return actividadEconomicaCodigo; }
    public void setActividadEconomicaCodigo(String actividadEconomicaCodigo) { this.actividadEconomicaCodigo = actividadEconomicaCodigo; }

    public String getActividadEconomicaDescripcion() { return actividadEconomicaDescripcion; }
    public void setActividadEconomicaDescripcion(String actividadEconomicaDescripcion) { this.actividadEconomicaDescripcion = actividadEconomicaDescripcion; }

    public String getTimbrado() { return timbrado; }
    public void setTimbrado(String timbrado) { this.timbrado = timbrado; }

    public String getFechaInicioTimbrado() { return fechaInicioTimbrado; }
    public void setFechaInicioTimbrado(String fechaInicioTimbrado) { this.fechaInicioTimbrado = fechaInicioTimbrado; }

    public String getEstablecimiento() { return establecimiento; }
    public void setEstablecimiento(String establecimiento) { this.establecimiento = establecimiento; }

    public String getPuntoExpedicion() { return puntoExpedicion; }
    public void setPuntoExpedicion(String puntoExpedicion) { this.puntoExpedicion = puntoExpedicion; }

    public String getNumeroDocumento() { return numeroDocumento; }
    public void setNumeroDocumento(String numeroDocumento) { this.numeroDocumento = numeroDocumento; }

    public String getSerie() { return serie; }
    public void setSerie(String serie) { this.serie = serie; }

    public String getDireccionEmisor() { return direccionEmisor; }
    public void setDireccionEmisor(String direccionEmisor) { this.direccionEmisor = direccionEmisor; }

    public String getNumeroCasaEmisor() { return numeroCasaEmisor; }
    public void setNumeroCasaEmisor(String numeroCasaEmisor) { this.numeroCasaEmisor = numeroCasaEmisor; }

    public Integer getCodigoDepartamentoEmisor() { return codigoDepartamentoEmisor; }
    public void setCodigoDepartamentoEmisor(Integer codigoDepartamentoEmisor) { this.codigoDepartamentoEmisor = codigoDepartamentoEmisor; }

    public String getDescripcionDepartamentoEmisor() { return descripcionDepartamentoEmisor; }
    public void setDescripcionDepartamentoEmisor(String descripcionDepartamentoEmisor) { this.descripcionDepartamentoEmisor = descripcionDepartamentoEmisor; }

    public Integer getCodigoDistritoEmisor() { return codigoDistritoEmisor; }
    public void setCodigoDistritoEmisor(Integer codigoDistritoEmisor) { this.codigoDistritoEmisor = codigoDistritoEmisor; }

    public String getDescripcionDistritoEmisor() { return descripcionDistritoEmisor; }
    public void setDescripcionDistritoEmisor(String descripcionDistritoEmisor) { this.descripcionDistritoEmisor = descripcionDistritoEmisor; }

    public Integer getCodigoCiudadEmisor() { return codigoCiudadEmisor; }
    public void setCodigoCiudadEmisor(Integer codigoCiudadEmisor) { this.codigoCiudadEmisor = codigoCiudadEmisor; }

    public String getDescripcionCiudadEmisor() { return descripcionCiudadEmisor; }
    public void setDescripcionCiudadEmisor(String descripcionCiudadEmisor) { this.descripcionCiudadEmisor = descripcionCiudadEmisor; }

    public String getTelefonoEmisor() { return telefonoEmisor; }
    public void setTelefonoEmisor(String telefonoEmisor) { this.telefonoEmisor = telefonoEmisor; }

    public String getEmailEmisor() { return emailEmisor; }
    public void setEmailEmisor(String emailEmisor) { this.emailEmisor = emailEmisor; }

    public Integer getTipoDocumento() { return tipoDocumento; }
    public void setTipoDocumento(Integer tipoDocumento) { this.tipoDocumento = tipoDocumento; }

    public String getFechaEmision() { return fechaEmision; }
    public void setFechaEmision(String fechaEmision) { this.fechaEmision = fechaEmision; }

    public Integer getTipoEmision() { return tipoEmision; }
    public void setTipoEmision(Integer tipoEmision) { this.tipoEmision = tipoEmision; }

    public Integer getTipoTransaccion() { return tipoTransaccion; }
    public void setTipoTransaccion(Integer tipoTransaccion) { this.tipoTransaccion = tipoTransaccion; }

    public Integer getTipoImpuesto() { return tipoImpuesto; }
    public void setTipoImpuesto(Integer tipoImpuesto) { this.tipoImpuesto = tipoImpuesto; }

    public String getMoneda() { return moneda; }
    public void setMoneda(String moneda) { this.moneda = moneda; }

    public BigDecimal getTasaCambio() { return tasaCambio; }
    public void setTasaCambio(BigDecimal tasaCambio) { this.tasaCambio = tasaCambio; }

    public String getCondicion() { return condicion; }
    public void setCondicion(String condicion) { this.condicion = condicion; }

    public String getPlazoCredito() { return plazoCredito; }
    public void setPlazoCredito(String plazoCredito) { this.plazoCredito = plazoCredito; }

    public String getCdcDocumentoAsociado() { return cdcDocumentoAsociado; }
    public void setCdcDocumentoAsociado(String cdcDocumentoAsociado) { this.cdcDocumentoAsociado = cdcDocumentoAsociado; }

    public String getMotivoEmisionNota() { return motivoEmisionNota; }
    public void setMotivoEmisionNota(String motivoEmisionNota) { this.motivoEmisionNota = motivoEmisionNota; }

    public String getRucCliente() { return rucCliente; }
    public void setRucCliente(String rucCliente) { this.rucCliente = rucCliente; }

    public String getRazonSocialCliente() { return razonSocialCliente; }
    public void setRazonSocialCliente(String razonSocialCliente) { this.razonSocialCliente = razonSocialCliente; }

    public String getDireccionCliente() { return direccionCliente; }
    public void setDireccionCliente(String direccionCliente) { this.direccionCliente = direccionCliente; }

    public String getTelefonoCliente() { return telefonoCliente; }
    public void setTelefonoCliente(String telefonoCliente) { this.telefonoCliente = telefonoCliente; }

    public String getEmailCliente() { return emailCliente; }
    public void setEmailCliente(String emailCliente) { this.emailCliente = emailCliente; }

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

    public String getObservacion() { return observacion; }
    public void setObservacion(String observacion) { this.observacion = observacion; }

    public List<ItemDeDto> getItems() { return items; }
    public void setItems(List<ItemDeDto> items) { this.items = items; }
}
