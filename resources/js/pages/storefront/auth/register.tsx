import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CustomerRegister() {
    return (
        <div className="mx-auto max-w-sm py-8">
            <Head title="Crear cuenta" />
            <h1 className="mb-6 text-2xl font-semibold">Crear cuenta</h1>

            <Form action="/cuenta/registro" method="post" className="space-y-4">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" name="name" required autoFocus />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input id="email" name="email" type="email" required />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Teléfono (opcional)</Label>
                            <Input id="phone" name="phone" />
                            <InputError message={errors.phone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Contraseña</Label>
                            <Input id="password" name="password" type="password" required />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                            <Input id="password_confirmation" name="password_confirmation" type="password" required />
                        </div>
                        <Button className="w-full" disabled={processing}>Crear cuenta</Button>
                    </>
                )}
            </Form>

            <p className="mt-4 text-center text-sm text-neutral-500">
                ¿Ya tienes cuenta?{' '}
                <Link href="/cuenta/login" className="hover:underline">Inicia sesión</Link>
            </p>
        </div>
    );
}
