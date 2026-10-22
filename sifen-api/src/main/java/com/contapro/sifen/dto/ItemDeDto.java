package com.contapro.sifen.dto;

import java.math.BigDecimal;

public class ItemDeDto {
    private String codigo;
    private String descripcion;
    private String unidadMedida = "77"; // 77 = Unidad en catálogo SIFEN
    private BigDecimal cantidad = BigDecimal.ONE;
    private BigDecimal precioUnitario = BigDecimal.ZERO;
    private BigDecimal descuento = BigDecimal.ZERO;
    private Integer tasaIva = 10; // 10, 5 o 0 (Exenta)

    public String getCodigo() { return codigo; }
    public void setCodigo(String codigo) { this.codigo = codigo; }

    public String getDescripcion() { return descripcion; }
    public void setDescripcion(String descripcion) { this.descripcion = descripcion; }

    public String getUnidadMedida() { return unidadMedida; }
    public void setUnidadMedida(String unidadMedida) { this.unidadMedida = unidadMedida; }

    public BigDecimal getCantidad() { return cantidad; }
    public void setCantidad(BigDecimal cantidad) { this.cantidad = cantidad; }

    public BigDecimal getPrecioUnitario() { return precioUnitario; }
    public void setPrecioUnitario(BigDecimal precioUnitario) { this.precioUnitario = precioUnitario; }

    public BigDecimal getDescuento() { return descuento; }
    public void setDescuento(BigDecimal descuento) { this.descuento = descuento; }

    public Integer getTasaIva() { return tasaIva; }
    public void setTasaIva(Integer tasaIva) { this.tasaIva = tasaIva; }
}
