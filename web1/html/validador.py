#!/usr/bin/env python3
import sys
import pyotp

def main():
    # Validar que se reciban exactamente los argumentos esperados (script, secret, codigo)
    if len(sys.argv) != 3:
        print("False")
        sys.exit(1)

    secret = sys.argv[1]
    code = sys.argv[2]

    try:
        # Inicializar TOTP con la librería pyotp
        totp = pyotp.TOTP(secret)
        
        # Verificar el código proporcionado con una ventana de tolerancia estándar (valid_window=1)
        if totp.verify(code, valid_window=1):
            print("True")
        else:
            print("False")
    except Exception:
        print("False")

if __name__ == '__main__':
    main()
