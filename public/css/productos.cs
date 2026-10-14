body {
    margin: 0;
    padding: 0;
}

.main-content {
    margin-left: 260px; 
    width: calc(100% - 260px);
    background-color: #f9fafb;
    min-height: 100vh;
    padding: 2.5rem !important; 
    box-sizing: border-box;
    display: block;
}

/* Tarjetas de Productos */
.producto-card {
    background: white;
    border-radius: 2.5rem; 
    padding: 1.75rem;
    transition: all 0.3s ease;
    border: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.producto-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
    border-color: #ffe4e6;
}

.img-container {
    background: #f8fafc;
    border-radius: 2rem;
    height: 200px;
    width: 100%;
    overflow: hidden;
    margin-bottom: 1.5rem;
    position: relative;
}

.img-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.producto-card:hover .img-container img {
    transform: scale(1.08);
}

#contenedor-dinamico nav {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    width: 100% !important;
    margin-top: 2.5rem !important;
    margin-bottom: 1.5rem !important;
}

#contenedor-dinamico .pagination {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
}

#contenedor-dinamico .pagination li {
    display: inline-block !important;
    margin: 0 !important;
    padding: 0 !important;
}

#contenedor-dinamico nav a,
#contenedor-dinamico nav span,
#contenedor-dinamico .pagination li a,
#contenedor-dinamico .pagination li span {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 40px !important;
    height: 40px !important;
    padding: 0 14px !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    color: #e11d48 !important;             
    background-color: #fff1f2 !important;   
    text-decoration: none !important;
    transition: all 0.2s ease-in-out !important;
    box-sizing: border-box !important;
    border: none !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02) !important;
    cursor: pointer;
}

#contenedor-dinamico nav a:hover,
#contenedor-dinamico .pagination li a:hover {
    background-color: #ffe4e6 !important;   
    transform: translateY(-2px) !important;  
}

#contenedor-dinamico .active a,
#contenedor-dinamico .active span,
#contenedor-dinamico li.active a,
#contenedor-dinamico li.active span {
    background-color: #e11d48 !important;    
    color: #ffffff !important;              
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25) !important; 
    transform: none !important;
    cursor: default !important;
}

#contenedor-dinamico .disabled a,
#contenedor-dinamico .disabled span,
#contenedor-dinamico li.disabled a,
#contenedor-dinamico li.disabled span {
    opacity: 0.4 !important;
    background-color: #f3f4f6 !important;    
    color: #9ca3af !important;              
    cursor: not-allowed !important;
    transform: none !important;
}

@media (max-width: 1024px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1.5rem !important;
    }
}
